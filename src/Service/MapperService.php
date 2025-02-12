<?php

namespace App\Service;

use App\Entity\DownloadCounter;
use App\Entity\Song;
use App\Entity\Utilisateur;
use App\Entity\Vote;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\VarDumper\VarDumper;

class MapperService
{

    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getTotalDownloads(UserInterface $user)
    {
        $res = $this->em->getRepository(Song::class)->createQueryBuilder('s')
            ->select("SUM(s.downloads)")
            ->where('s.user = :user')
            ->andWhere('s.isDeleted != 1')
            ->setParameter('user', $user)
            ->groupBy("s.user")
            ->getQuery()->getOneOrNullResult();
        return array_pop($res);
    }

    public function getTotalUpperVotes(UserInterface $user)
    {
        $res = $this->em->getRepository(Song::class)->createQueryBuilder('s')
            ->select("SUM(s.voteUp)")
            ->where('s.user = :user')
            ->andWhere('s.isDeleted != 1')
            ->setParameter('user', $user)
            ->groupBy("s.user")
            ->getQuery()->getOneOrNullResult();
        return array_pop($res);
    }

    public function getTotalLowerVotes(UserInterface $user)
    {
        $res = $this->em->getRepository(Song::class)->createQueryBuilder('s')
            ->select("SUM(s.voteDown)")
            ->where('s.user = :user')
            ->andWhere('s.isDeleted != 1')
            ->setParameter('user', $user)
            ->groupBy("s.user")
            ->getQuery()->getOneOrNullResult();
        return array_pop($res);
    }

    public function getAvgReview(UserInterface $user)
    {
        $res = $this->em->getRepository(Song::class)->createQueryBuilder('s')
            ->select("SUM(s.totalVotes)/SUM(s.countVotes)")
            ->where('s.user = :user')
            ->andWhere('s.isDeleted != 1')
            ->andWhere('s.countVotes != 0')
            ->setParameter('user', $user)
            ->groupBy("s.user")
            ->getQuery()->getOneOrNullResult();
        return array_pop($res);
    }

    public function getTotalReview(UserInterface $user)
    {
        $res = $this->em->getRepository(Song::class)->createQueryBuilder('s')
            ->select("SUM(s.countVotes)")
            ->where('s.user = :user')
            ->andWhere('s.isDeleted != 1')
            ->andWhere('s.countVotes != 0')
            ->setParameter('user', $user)
            ->groupBy("s.user")
            ->getQuery()->getOneOrNullResult();
        return array_pop($res);
    }

    public function getAvgFunFactor(UserInterface $user)
    {
        $res = $this->em->getRepository(Vote::class)->createQueryBuilder('v')
            ->join('v.song','s')
            ->select("SUM(v.funFactor)/COUNT(v.funFactor)")
            ->where('s.user = :user')
            ->andWhere('s.isDeleted != 1')
            ->andWhere('s.countVotes != 0')
            ->setParameter('user', $user)
            ->groupBy("s.user")
            ->getQuery()->getOneOrNullResult();
        return array_pop($res);
    }

    public function getAvgRhythm(UserInterface $user)
    {
        $res = $this->em->getRepository(Vote::class)->createQueryBuilder('v')
            ->join('v.song','s')
            ->select("SUM(v.rhythm)/COUNT(v.rhythm)")
            ->where('s.user = :user')
            ->andWhere('s.isDeleted != 1')
            ->andWhere('s.countVotes != 0')
            ->setParameter('user', $user)
            ->groupBy("s.user")
            ->getQuery()->getOneOrNullResult();
        return array_pop($res);
    }
    public function getAvgPatternQuality(UserInterface $user)
    {
        $res = $this->em->getRepository(Vote::class)->createQueryBuilder('v')
            ->join('v.song','s')
            ->select("SUM(v.patternQuality)/COUNT(v.patternQuality)")
            ->where('s.user = :user')
            ->andWhere('s.isDeleted != 1')
            ->andWhere('s.countVotes != 0')
            ->setParameter('user', $user)
            ->groupBy("s.user")
            ->getQuery()->getOneOrNullResult();
        return array_pop($res);
    }
    public function getAvgReadability(UserInterface $user)
    {
        $res = $this->em->getRepository(Vote::class)->createQueryBuilder('v')
            ->join('v.song','s')
            ->select("SUM(v.readability)/COUNT(v.readability)")
            ->where('s.user = :user')
            ->andWhere('s.isDeleted != 1')
            ->andWhere('s.countVotes != 0')
            ->setParameter('user', $user)
            ->groupBy("s.user")
            ->getQuery()->getOneOrNullResult();
        return array_pop($res);
    }
}

