<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\LoginHistoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGER')]
final class ManagerController extends AbstractController
{
    #[Route('/manager', name: 'app_manager')]
    public function index(
        UserRepository $userRepository,
        LoginHistoryRepository $loginHistoryRepository
    ): Response {
        $users = $userRepository->findBy([], ['id' => 'ASC']);

        $loginHistoriesByUserId = [];
        $loginHistories = $loginHistoryRepository->findBy([], ['loggedAt' => 'DESC']);

        foreach ($loginHistories as $loginHistory) {
            $userId = $loginHistory->getUser()->getId();
            $loginHistoriesByUserId[$userId][] = $loginHistory;
        }

        return $this->render('manager/index.html.twig', [
            'users' => $users,
            'loginHistoriesByUserId' => $loginHistoriesByUserId,
        ]);
    }
}