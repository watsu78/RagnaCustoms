<?php

namespace App\Controller;

use App\Entity\Overlay;
use App\Entity\RankedScores;
use App\Entity\Score;
use App\Entity\ScoreHistory;
use App\Entity\Song;
use App\Entity\SongDifficulty;
use App\Entity\Utilisateur;
use App\Repository\DifficultyRankRepository;
use App\Repository\OverlayRepository;
use App\Repository\RankedScoresRepository;
use App\Repository\ScoreHistoryRepository;
use App\Repository\ScoreRepository;
use App\Repository\SongCategoryRepository;
use App\Repository\SongDifficultyRepository;
use App\Repository\SongRepository;
use App\Repository\UtilisateurRepository;
use App\Service\ScoreService;
use App\Service\SongService;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Sentry\State\Scope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use function Sentry\configureScope;


class WanadevApiController extends AbstractController
{


    /**
     * @Route("/wd-api/score/{apiKey}", name="wd_api_score")
     */
    public function score(Request $request, string $apiKey, DifficultyRankRepository $difficultyRankRepository, SongDifficultyRepository $songDifficultyRepository, ScoreRepository $scoreRepository, ScoreHistoryRepository $scoreHistoryRepository, UtilisateurRepository $utilisateurRepository, SongRepository $songRepository, SongService $songService, ScoreService $scoreService, RankedScoresRepository $rankedScoresRepository, LoggerInterface $logger): Response
    {
        $em = $this->getDoctrine()->getManager();
        $results = [];
        $ranked = false;

        $data = json_decode($request->getContent(), true);
        $data = $data[0];
        if ($data == null) {
            $logger->error("no data");
            $results[] = [
                "user" => $apiKey,
                "hash" => "all",
                "ranked" => $ranked,
                "level" => "",
                "message" => "Score not saved (no data) ",
                "success" => false,
                "error" => "0_NO_CONTENT"
            ];
            return new JsonResponse($results, 500);
        }

        /** @var Utilisateur $user */
        $user = $utilisateurRepository->findOneBy(['apiKey' => $apiKey]);
        if ($user == null) {
            $results[] = [
                "user" => $apiKey,
                "hash" => "all",
                "level" => "",
                "ranked" => $ranked,
                "message" => "Score not saved (user not found) ",
                "success" => false,
                "error" => "0_USER_NOT_FOUND"
            ];
            $logger->error("API : " . $apiKey . " USER NOT FOUND");
            return new JsonResponse($results, 400);
        }

        configureScope(function (Scope $scope) use ($user): void {
            $scope->setUser(['username' => $user->getUsername()]);
        });

        if ($data["AppVersion"] < self::CurrentVersion) {
            $results[] = [
                "user" => $apiKey,
                "hash" => "all",
                "ranked" => $ranked,
                "level" => "",
                "message" => "Score not saved (wrong app version, need : " . (self::CurrentVersion) . " get at least " . $data["AppVersion"] . " )",
                "success" => false,
                "error" => "0_WRONG_APP"
            ];
        }
        $hash = $data["HashInfo"];
        $level = $data["Level"];

        try {
            $song = $songRepository->findOneBy(['newGuid' => $hash]);
            if ($song == null) {
                $results[] = [
                    "hash" => $hash,
                    "level" => $level,
                    "message" => "Score not saved (song not found) ",
                    "ranked" => $ranked,
                    "success" => false,
                    "error" => "1_SONG_NOT_FOUND"
                ];
                $logger->error("API : " . $apiKey . " " . $hash . " 1_SONG_NOT_FOUND");
                return new JsonResponse($results, 400);
            }
            $rank = $difficultyRankRepository->findOneBy(['level' => $level]);
            $songDiff = $songDifficultyRepository->findOneBy([
                'song' => $song,
                "difficultyRank" => $rank
            ]);

            if ($songDiff == null) {
                $results[] = [
                    "hash" => $hash,
                    "level" => $level,
                    "ranked" => $ranked,
                    "message" => "Score not saved (level not found) ",
                    "success" => false,
                    "error" => "2_LEVEL_NOT_FOUND"
                ];
                $logger->error("API : " . $apiKey . " " . $hash . " " . $level . " 2_LEVEL_NOT_FOUND");
                return new JsonResponse($results, 400);
            }
            if ($songDiff->getTheoricalMaxScore() <= 0) {
                $songDiff->setTheoricalMaxScore($songService->calculateTheoricalMaxScore($songDiff));
            }

            $score = $scoreRepository->findOneBy([
                'user' => $user,
                'difficulty' => $level,
                'hash' => $hash,
                'season' => null
            ]);
            $scoreData = round(floatval($data['Score']) / 100, 2);

            if ($score == null) {
                $score = new Score();
                $score->setUser($user);
                $score->setScore($scoreData);
                $score->setDifficulty($level);
                $score->setSong($song);
                $score->setSongDifficulty($songDiff);
                $score->setHash($hash);
                $score->setPercentage($data["Percentage"] ?? null);
                $score->setPercentage2($data["Percentage2"] ?? null);
                $score->setCombos($data["Combos"] ?? null);
                $score->setNotesHit($data["NotesHit"] ?? null);
                $score->setNotesMissed($data["NotesMissed"] ?? null);
                $score->setNotesNotProcessed($data["NotesNotProcessed"] ?? null);
                $score->setHitAccuracy($data["HitAccuracy"] ?? null);
                $score->setHitSpeed($data["HitSpeed"] ?? null);
                if ($songDiff->isRanked()) {
                    $rawPP = $this->calculateRawPP($score, $songDiff);
                    $score->setRawPP($rawPP);
                }
                $em->persist($score);
            }

            $oldscore = $score->getScore();
            if ($score->getScore() <= $scoreData) {
                $score->setScore($scoreData);
                $score->setPercentage($data["Percentage"] ?? null);
                $score->setPercentage2($data["Percentage2"] ?? null);
                $score->setCombos($data["Combos"] ?? null);
                $score->setNotesHit($data["NotesHit"] ?? null);
                $score->setNotesMissed($data["NotesMissed"] ?? null);
                $score->setNotesNotProcessed($data["NotesNotProcessed"] ?? null);
                $score->setHitAccuracy($data["HitAccuracy"] ?? null);
                $score->setHitSpeed($data["HitSpeed"] ?? null);
                if ($score->getScore() >= 99000) {
                    $score->setScore($score->getScore() / 1000000);
                }
                if ($songDiff->isRanked()) {
                    $rawPP = $this->calculateRawPP($score, $songDiff);
                    $score->setRawPP($rawPP);
                }

                $em->flush();

                $results[] = [
                    "hash" => $hash,
                    "level" => $level,
                    "success" => true,
                    "ranked" => $ranked,
                    "message" => "Score saved (old score : " . $oldscore . " < new score : " . $scoreData . ") ",
                    "error" => "SUCCESS"
                ];
            } else {
                $em->flush();
                $results[] = [
                    "hash" => $hash,
                    "level" => $level,
                    "success" => true,
                    "ranked" => $ranked,
                    "message" => "Score not saved (old score : " . $oldscore . " >= new score : " . $scoreData . ")",
                    "error" => "SUCCESS"
                ];
            }
            $scoreService->archive($score);

            $results[] = [
                "hash" => $hash,
                "level" => $level,
                "success" => true,
                "message" => "Score saved",
                "error" => "SUCCESS"
            ];
        } catch (Exception $e) {
            $results[] = [
                "hash" => $hash,
                "level" => $level,
                "success" => false,
                "error" => "3_SCORE_NOT_SAVED",
                "message" => "Score not saved because of an unexpected error",
                'detail' => $e->getMessage()
            ];
            $logger->error("API : " . $apiKey . " " . $hash . " " . $data["Level"] . " 3_SCORE_NOT_SAVED : " . $e->getMessage() . " ");
            return new JsonResponse($results, 400);
        }

        //calculation of the ponderate PP scores
        if ($songDiff->isRanked()) {
            $totalPondPPScore = $this->calculateTotalPondPPScore($scoreRepository, $user);

            //insert/update of the score into ranked_scores
            $rankedScore = $rankedScoresRepository->findOneBy([
                'user' => $user
            ]);

            if ($rankedScore == null) {
                $logger->error("null");
            } else {
                $logger->error("ID : " . $rankedScore->getId() . " / USER : " . $rankedScore->getUser()->getId());
            }

            if ($rankedScore == null) {
                $rankedScore = new RankedScores();
                $rankedScore->setUser($user);
                $rankedScore->setTotalPPScore($totalPondPPScore);
                $em->persist($rankedScore);
            }
            $rankedScore->setTotalPPScore($totalPondPPScore);
            $em->flush();
        }

        return new JsonResponse($results, 200);
    }

    private function calculateRawPP(Score $score, SongDifficulty $songDiff)
    {
        $userScore = $score->getScore();
        $songLevel = $score->getDifficulty();
        $maxSongScore = $songDiff->getTheoricalMaxScore();
        // raw pp is calculated by making the ratio between the current score and the theoretical maximum score.
        // it is ponderated by the song level
        $rawPP = (($userScore / $maxSongScore) * (0.4 + 0.1 * $songLevel)) * 100;

        return round($rawPP, 2);
    }

    private function calculateTotalPondPPScore(ScoreRepository $scoreRepository, Utilisateur $user)
    {
        $totalPP = 0;
        $scores = $scoreRepository->createQueryBuilder('score')->leftJoin('score.songDifficulty', 'diff')->where('score.user = :user')->andWhere('diff.isRanked = true')->setParameter('user', $user)->addOrderBy('score.rawPP', 'desc')->getQuery()->getResult();

        $index = 0;
        foreach ($scores as $score) {
            $rawPPScore = $score->getRawPP();
            $pondPPScore = $rawPPScore * pow(0.965, $index);
            $totalPP = $totalPP + $pondPPScore;
            $index++;
        }

        return round($totalPP, 2);
    }

    /**
     * @Route("/api/search/{term}", name="api_search")
     */
    public function index(Request $request, string $term = null, SongRepository $songRepository): Response
    {
        $songsEntities = $songRepository->createQueryBuilder('s')->where('(s.name LIKE :search_string OR s.authorName LIKE :search_string OR s.levelAuthorName LIKE :search_string)')->andWhere('s.moderated = true')->andWhere('s.isDeleted != true')->setParameter('search_string', '%' . $term . '%')->getQuery()->getResult();
        $songs = [];

        /** @var Song $song */
        foreach ($songsEntities as $song) {
            $songs[] = [
                "Id" => $song->getId(),
                "Name" => $song->getName(),
                "IsRanked" => $song->isSeasonRanked(),
                "Hash" => $song->getNewGuid(),
                "Author" => $song->getAuthorName(),
                "Mapper" => $song->getLevelAuthorName(),
                "Difficulties" => $song->getSongDifficultiesStr(),
                "CoverImageExtension" => $song->getCoverImageExtension(),
            ];
        }

        return new JsonResponse([
                "Results" => $songs,
                "Count" => count($songs)
            ]);
    }

    /**
     * @Route("/api/song/{id}", name="api_song")
     */
    public function song(Request $request, Song $song): Response
    {
        return new JsonResponse([
                "Id" => $song->getId(),
                "Name" => $song->getName(),
                "Author" => $song->getAuthorName(),
                "IsRanked" => $song->isSeasonRanked(),
                "Hash" => $song->getNewGuid(),
                "Mapper" => $song->getLevelAuthorName(),
                "Difficulties" => $song->getSongDifficultiesStr(),
                "CoverImageExtension" => $song->getCoverImageExtension(),
            ]);
    }

    /**
     * @Route("/api/hash/{hash}", name="api_hash")
     */
    public function hash(Request $request, string $hash, SongRepository $songRepository): Response
    {
        $song = $songRepository->createQueryBuilder('s')->where('s.newGuid LIKE :search_string)')->andWhere('s.moderated = true')->setParameter('search_string', $hash)->getQuery()->setFirstResult(0)->setMaxResults(1)->getOneOrNullResult();
        if (!$song) {
            return new Response("NOK", 400);
        }
        return new JsonResponse([
                "Id" => $song->getId(),
                "Name" => $song->getName(),
                "Author" => $song->getAuthorName(),
                "IsRanked" => $song->isSeasonRanked(),
                "Hash" => $song->getNewGuid(),
                "Mapper" => $song->getLevelAuthorName(),
                "Difficulties" => $song->getSongDifficultiesStr(),
                "CoverImageExtension" => $song->getCoverImageExtension(),
            ]);
    }

    /**
     * @Route("/api/overlay/", name="api_overlay")
     * @param Request $request
     * @param UtilisateurRepository $utilisateurRepository
     * @param DifficultyRankRepository $difficultyRankRepository
     * @param OverlayRepository $overlayRepository
     * @param SongRepository $songRepository
     * @param SongDifficultyRepository $songDifficultyRepository
     * @return Response
     */
    public function overlay(Request $request, UtilisateurRepository $utilisateurRepository, DifficultyRankRepository $difficultyRankRepository, OverlayRepository $overlayRepository, SongRepository $songRepository, SongDifficultyRepository $songDifficultyRepository): Response
    {
        $data = json_decode($request->getContent(), true);
        $apiKey = $request->headers->get('x-api-key');

        if ($data == null) {
            return new Response("NOK", 500);
        }

        $user = $utilisateurRepository->findOneBy(['apiKey' => $apiKey]);
        $em = $this->getDoctrine()->getManager();
        if ($user == null) {
            return new Response("NO USER", 500);
        }

        $overlay = $overlayRepository->findOneBy(["user" => $user]);
        if ($overlay == null) {
            $overlay = new Overlay();
            $overlay->setUser($user);
            $em->persist($overlay);
            $em->flush();
        }
        $song = $songRepository->findOneBy(['newGuid' => $data["Song"]["Hash"]]);
        if ($song == null) {
            $overlay->setDifficulty(null);
            $overlay->setStartAt(null);
            $em->flush();
            return new Response("NOK", 500);
        }
        $rank = $difficultyRankRepository->findOneBy(['level' => $data["Song"]['Level']]);
        $songDiff = $songDifficultyRepository->findOneBy([
            'song' => $song,
            "difficultyRank" => $rank
        ]);

        if ($songDiff == null) {
            $overlay->setDifficulty(null);
            $overlay->setStartAt(null);
            $em->flush();
            return new Response("NOK", 500);
        }

        $overlay->setDifficulty($songDiff);
        $overlay->setStartAt(new DateTime());
        $em->flush();

        return new Response("OK");
    }

    /**
     * @Route("/api/overlay/clean/", name="api_overlay_clear")
     * @param Request $request
     * @param UtilisateurRepository $utilisateurRepository
     * @param OverlayRepository $overlayRepository
     * @return Response
     */
    public function overlayClean(Request $request, UtilisateurRepository $utilisateurRepository, OverlayRepository $overlayRepository): Response
    {
        $apiKey = $request->headers->get('x-api-key');

        $user = $utilisateurRepository->findOneBy(['apiKey' => $apiKey]);
        $em = $this->getDoctrine()->getManager();
        if ($user == null) {
            return new Response("NO USER", 500);
        }

        $overlay = $overlayRepository->findOneBy(["user" => $user]);
        if ($overlay == null) {
            $overlay = new Overlay();
            $overlay->setUser($user);
            $em->persist($overlay);
            $em->flush();
        }
        $overlay->setDifficulty(null);
        $overlay->setStartAt(null);
        $em->flush();
        return new Response("OK");
    }

    /**
     * @param Request $request
     * @Route("/api/song-categories", name="api_song_categories")
     */
    public function songCategories(Request $request, SongCategoryRepository $categoryRepository)
    {
        $data = $categoryRepository->createQueryBuilder("sc")->select("sc.id AS id, sc.label AS text")->where('sc.label LIKE :search')->setParameter('search', '%' . $request->get('q') . '%')->andWhere('sc.isOnlyForAdmin = false')->orderBy('sc.label')->getQuery()->getArrayResult();

        return new JsonResponse([
            'results' => $data
        ]);
    }

}