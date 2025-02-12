<?php

namespace App\Controller;

use App\Entity\ScoreHistory;
use App\Entity\Song;
use App\Entity\SongHash;
use App\Entity\Utilisateur;
use App\Enum\EGamification;
use App\Form\UtilisateurType;
use App\Repository\ScoreHistoryRepository;
use App\Repository\ScoreRepository;
use App\Repository\SongHashRepository;
use App\Repository\SongRepository;
use App\Repository\UtilisateurRepository;
use App\Service\GamificationService;
use App\Service\StatisticService;
use Pkshetlie\PaginationBundle\Service\PaginationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\VarDumper\VarDumper;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserController extends AbstractController
{
    /**
     * @Route("/user-profile/{username}", name="user_profile")
     * @param Request $request
     * @param Utilisateur $utilisateur
     * @param PaginationService $paginationService
     * @param StatisticService $statisticService
     * @param ScoreRepository $scoreRepository
     * @param ScoreHistoryRepository $scoreHistoryRepository
     * @param UtilisateurRepository $utilisateurRepository
     * @param GamificationService $gamificationService
     * @return Response
     */
    public function profile(Request $request, Utilisateur $utilisateur, PaginationService $paginationService, StatisticService $statisticService, ScoreRepository $scoreRepository, ScoreHistoryRepository $scoreHistoryRepository, UtilisateurRepository $utilisateurRepository, GamificationService $gamificationService): Response
    {


        $qb = $scoreHistoryRepository->createQueryBuilder('s')->where('s.user = :user')->setParameter('user', $utilisateur)->orderBy('s.updatedAt', "desc");
        $pagination = $paginationService->setDefaults(15)->process($qb, $request);

        return $this->render('user/partial/song_played.html.twig', [
            'controller_name' => 'UserController',
            'pagination' => $pagination,
            'user' => $utilisateur,
            'mapperProfile' => false,
        ]);
    }

    /**
     * @Route("/user/progess/{id}/{level}", name="user_progress_song")
     */
    public function progressSong(Request $request, Song $song, string $level, Utilisateur $utilisateur, ScoreHistoryRepository $scoreHistoryRepository): Response
    {
        $hashes = $song->getHashes();

        $scores = $scoreHistoryRepository->createQueryBuilder('score_history')->where('score_history.user = :user')->andWhere("score_history.hash IN (:hashes)")->andWhere("score_history.difficulty = :level")->setParameter("user", $this->getUser())->setParameter("hashes", $hashes)->setParameter("level", $level)->orderBy("score_history.updatedAt", "ASC")->getQuery()->getResult();

        $labels = [];
        $data = [];
        /** @var ScoreHistory $score */
        foreach ($scores as $score) {
            $labels[] = $score->getUpdatedAt()->format("Y-d-m H:i");
            $data[] = $score->getScore();
            $notesHit[] = $score->getNotesHit() ?? 0;
        }


        return $this->render('user/progress.html.twig', [
            'controller_name' => 'UserController',
            'scores' => $scores,
            "song" => $song,
            "level" => $level,
            "labels" => $labels,
            "data" => $data,
            "notesHit" => $notesHit,
        ]);
    }

    /**
     * @Route("/mapper-profile/{username}", name="mapper_profile")
     */
    public function mappedProfile(Request $request, Utilisateur $utilisateur, SongRepository $songRepository, PaginationService $pagination): Response
    {
        $qb = $this->getDoctrine()
            ->getRepository(Song::class)
            ->createQueryBuilder("s")
            ->where('s.user = :user')
            ->setParameter('user', $utilisateur)
            ->addSelect('s.voteUp - s.voteDown AS HIDDEN rating')
//            ->leftJoin("s.downloadCounters",'dc')
            ->groupBy("s.id");
//        $qb->leftJoin('s.songDifficulties', 'song_difficulties')
//            ->leftJoin('song_difficulties.difficultyRank', 'rank');
//        $qb->addSelect('s,song_difficulties');

        if ($request->get('display_wip', null) != null) {
            $qb->andWhere("s.wip = true");
        }else{
            $qb->andWhere("s.wip != true");
        }

        $qb->leftJoin('s.songDifficulties', 'song_difficulties');

        if ($request->get('only_ranked', null) != null) {
            $qb->andWhere("song_difficulties.isRanked = true");
        }
        if ($request->get('downloads_filter_difficulties', null)) {
            $qb->leftJoin('song_difficulties.difficultyRank', 'rank');
            switch ($request->get('downloads_filter_difficulties')) {
                case 1:
                    $qb->andWhere('rank.level BETWEEN 1 and 3');
                    break;
                case 2 :
                    $qb->andWhere('rank.level BETWEEN 4 and 7');
                    break;
                case 3 :
                    $qb->andWhere('rank.level BETWEEN 8 and 10');
                    break;
                case 6 :
                    $qb->andWhere('rank.level > 10');
                    break;
                case 4 :
                    $qb->leftJoin('song_difficulties.seasons', 'season');
                    $qb->andWhere('season.startDate <= :now ')
                        ->andWhere('season.endDate >= :now')
                        ->setParameter('now', new DateTime());
                    break;
                case 5 :
                    $wip = true;
                    break;
            }
        }


        $categories = $request->get('downloads_filter_categories', null);
        if ($categories != null) {
            $qb->leftJoin('s.categoryTags', 't');
            foreach ($categories as $k => $v) {
                $qb->andWhere("t.id = :tag$k")
                    ->setParameter("tag$k", $v);
            }
        }

        if ($request->get('downloads_filter_order', null)) {
            switch ($request->get('downloads_filter_order')) {
                case 1:
                    $qb->orderBy('s.voteUp - s.voteDown', 'DESC');
                    break;
                case 2 :
                    $qb->orderBy('s.approximativeDuration', 'DESC');
                    break;
                case 3 :
                    $qb->orderBy('s.lastDateUpload', 'DESC');
                    break;
                case 4 :
                    $qb->orderBy('s.name', 'ASC');
                    break;
                case 5 :
                    $qb->orderBy('s.downloads', 'DESC');

//                    $qb->addSelect("COUNT(dc.id) AS HIDDEN count_dl");
//                    $qb->groupBy("s.id");
//                    $qb->orderBy('count_dl', 'DESC');
                    break;
                default:
                    $qb->orderBy('s.lastDateUpload', 'DESC');
                    break;
            }
        } else {
            $qb->orderBy('s.createdAt', 'DESC');
        }


        if ($request->get('converted_maps', null)) {

            switch ($request->get('converted_maps')) {
                case 1:
                    $qb->andWhere('(s.converted = false OR s.converted IS NULL)');
                    break;
                case 2 :
                    $qb->andWhere('s.converted = true');
                    break;
            }
        }

        if ($request->get('downloads_submitted_date', null)) {

            switch ($request->get('downloads_submitted_date')) {
                case 1:
                    $qb->andWhere('(s.lastDateUpload >= :last7days)')
                        ->setParameter('last7days', (new DateTime())->modify('-7 days'));
                    break;
                case 2 :
                    $qb->andWhere('(s.lastDateUpload >= :last15days)')
                        ->setParameter('last15days', (new DateTime())->modify('-15 days'));
                    break;
                case 3 :
                    $qb->andWhere('(s.lastDateUpload >= :last45days)')
                        ->setParameter('last45days', (new DateTime())->modify('-45 days'));
                    break;
            }
        }
        if ($request->get('not_downloaded', 0) > 0 && $this->isGranted('ROLE_USER')) {
            $qb
                ->leftJoin("s.downloadCounters", 'download_counters')
                ->addSelect("SUM(IF(download_counters.user = :user,1,0)) AS HIDDEN count_download_user")
                ->andHaving("count_download_user = 0")
                ->setParameter('user', $this->getuser());
        }
        $qb->andWhere('s.moderated = true');

        //get the 'type' param (added for ajax search)
        $type = $request->get('type', null);
        //check if this is an ajax request
        $ajaxRequest = $type == 'ajax';
        //remove the 'type' parameter so pagination does not break
        if ($ajaxRequest) {
            $request->query->remove('type');
        }

        if ($request->get('search', null)) {
            $exp = explode(':', $request->get('search'));
            switch ($exp[0]) {
                case 'mapper':
                    if (count($exp) >= 2) {
                        $qb->andWhere('(s.levelAuthorName LIKE :search_string)')
                            ->setParameter('search_string', '%' . $exp[1] . '%');
                    }
                    break;
//                case 'category':
//                    if (count($exp) >= 1) {
//                        $qb->andWhere('(s.songCategory = :category)')
//                            ->setParameter('category', $exp[1] == "" ? null : $exp[1]);
//                    }
//                    break;
                case 'artist':
                    if (count($exp) >= 2) {
                        $qb->andWhere('(s.authorName LIKE :search_string)')
                            ->setParameter('search_string', '%' . $exp[1] . '%');
                    }
                    break;
                case 'title':
                    if (count($exp) >= 2) {
                        $qb->andWhere('(s.name LIKE :search_string)')
                            ->setParameter('search_string', '%' . $exp[1] . '%');
                    }
                    break;
                case 'desc':
                    if (count($exp) >= 2) {
                        $qb->andWhere('(s.description LIKE :search_string)')
                            ->setParameter('search_string', '%' . $exp[1] . '%');
                    }
                    break;
                default:
                    $qb->andWhere('(s.name LIKE :search_string OR s.authorName LIKE :search_string OR s.description LIKE :search_string OR s.levelAuthorName LIKE :search_string)')
                        ->setParameter('search_string', '%' . $request->get('search', null) . '%');
            }
        }
        $qb->andWhere("s.isDeleted != true");

        if ($request->get('onclick_dl')) {
            $ids = $qb->select('s.id')->getQuery()->getArrayResult();
            return $this->redirect("ragnac://install/" . implode('-', array_map(function ($id) {
                    return array_pop($id);
                }, $ids)));
        }

        if ($request->get('order_by')) {
            $qb->orderBy($request->get('order_by'), $request->get('order_sort', 'asc'));
        }
        //$pagination = null;
        //if($ajaxRequest || $request->get('ppage1')) {
        $songs = $pagination->setDefaults(15)->process($qb, $request);

        return $this->render('user/partial/song_mapped.html.twig', [
            'controller_name' => 'UserController',
            'user' => $utilisateur,
            'categories' => $categories,
            'songs' => $songs
        ]);
    }

    /**
     * @Route("/user", name="user")
     */
    public function index(Request $request, TranslatorInterface $translator, UtilisateurRepository $utilisateurRepository, ScoreHistoryRepository $scoreHistoryRepository, PaginationService $paginationService): Response
    {
        if (!$this->isGranted('ROLE_USER')) {
            $this->addFlash('danger', $translator->trans("You need an account to access this page."));
            return $this->redirectToRoute('home');
        }
        $em = $this->getDoctrine()->getManager();
        if ($this->getUser()->getApiKey() == null) {
            $this->getUser()->setApiKey(md5(date('d/m/Y H:i:s') . $this->getUser()->getUsername()));
        }
        $em->flush();
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $form = $this->createForm(UtilisateurType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $email_user = $utilisateurRepository->findOneBy(['email' => $user->getEmail()]);
            if ($email_user != null && $user->getId() !== $email_user->getId()) {
                $form->addError(new FormError("This email is already used."));
            } else {
                $email_user = $utilisateurRepository->findOneBy(['mapper_name' => $user->getMapperName()]);
                if ($email_user != null && $user->getId() !== $email_user->getId()) {
                    $form->addError(new FormError("This mapper name is already used."));
                } else {
                    $this->getDoctrine()->getManager()->flush();
                }
            }
        }

        $qb = $scoreHistoryRepository->createQueryBuilder('s')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.updatedAt', "desc");
        $pagination = $paginationService->setDefaults(25)->process($qb, $request);


        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
            'pagination' => $pagination,
            'form' => $form->createView()
        ]);
    }

}
