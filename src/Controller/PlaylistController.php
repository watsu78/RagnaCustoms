<?php

namespace App\Controller;

use App\Entity\Playlist;
use App\Repository\SongRepository;
use Pkshetlie\PaginationBundle\Service\PaginationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PlaylistController extends AbstractController
{
    /**
     * @Route("/playlists", name="playlist")
     */
    public function index(): Response
    {
        /** @var Playlist[] $playlists */
        $playlists = $this->getUser()->getPlaylists();

        return $this->render('playlist/index.html.twig', [
            'playlists' => $playlists,
        ]);
    }

    /**
     * @Route("/playlist/show/{id}", name="playlist_show")
     * @param Request $request
     * @param Playlist $playlist
     * @param PaginationService $paginationService
     * @param SongRepository $song
     * @return Response
     */
    public function show(Request $request, Playlist $playlist, PaginationService $paginationService, SongRepository $songRepository): Response
    {
        if (!$playlist->getIsPublic()) {
            $this->addFlash("warning", "This playlist is not public");
            return $this->redirectToRoute('home');
        }
        $qb = $songRepository->createQueryBuilder("s")
            ->leftJoin("s.playlists",'playlist')
            ->where('playlist = :playlist')
            ->setParameter("playlist", $playlist)
        ->addOrderBy('s.name');
        $songs = $paginationService->setDefaults(72)->process($qb, $request);
        return $this->render('playlist/show.html.twig', [
            'playlist' => $playlist,
            'songs' => $songs,
            "user"=>$playlist->getUser()

        ]);
    }
}
