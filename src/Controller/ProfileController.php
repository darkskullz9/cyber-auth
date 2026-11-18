<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\PasswordChangeFormType;
use App\Form\UserEditFormType;
use App\Service\PasswordHistoryManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;


final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig', [
            'controller_name' => 'ProfileController',
        ]);
    }

    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(UserEditFormType::class, $user, [
            'show_suspension_field' => false,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash(
                'success',
                'Vos informations personnelles ont été modifiées.'
            );

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'userForm' => $form,
        ]);
    }

    #[Route('/profile/password', name: 'app_change_password')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        PasswordHistoryManager $passwordHistoryManager,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(PasswordChangeFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            // Vérifier le mot de passe actuel.
            if (
                !is_string($currentPassword)
                || !$userPasswordHasher->isPasswordValid(
                    $user,
                    $currentPassword
                )
            ) {
                $form->get('currentPassword')->addError(
                    new FormError('Votre mot de passe actuel est incorrect.')
                );
            } elseif (is_string($newPassword)) {
                // Vérifier l'historique et modifier le mot de passe.
                $success = $passwordHistoryManager->changePassword(
                    $user,
                    $newPassword
                );

                if (!$success) {
                    $form->get('newPassword')->addError(
                        new FormError(
                            'Vous avez déjà utilisé ce mot de passe. Choisissez-en un autre.'
                        )
                    );
                } else {
                    $this->addFlash(
                        'success',
                        'Votre mot de passe a été modifié avec succès.'
                    );

                    return $this->redirectToRoute('app_profile');
                }
            }
        }

        return $this->render('profile/change_password.html.twig', [
            'passwordForm' => $form,
        ]);
    }
}
