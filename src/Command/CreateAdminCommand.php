<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an administrator account',
)]
class CreateAdminCommand{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
    ): int {
        $io->title('Création d\'un compte administrateur');

        // Informations personnelles
        $email = $io->ask('Adresse e-mail');
        $nom = $io->ask('Nom');
        $prenom = $io->ask('Prénom');
        $adresse = $io->ask('Adresse');
        $ville = $io->ask('Ville');
        $codePostal = $io->ask('Code postal');

        // Vérifier le format de l'adresse e-mail.
        if (
            !is_string($email)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $io->error('L’adresse e-mail saisie est invalide.');

            return Command::FAILURE;
        }

        // Vérifier que l'adresse e-mail n'existe pas déjà.
        $existingUser = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($existingUser !== null) {
            $io->error('Un compte existe déjà avec cette adresse e-mail.');

            return Command::FAILURE;
        }

        // Vérifier les informations personnelles.
        $fields = [
            'Nom' => [$nom, 100],
            'Prénom' => [$prenom, 100],
            'Adresse' => [$adresse, 255],
            'Ville' => [$ville, 100],
            'Code postal' => [$codePostal, 5],
        ];

        foreach ($fields as $label => [$value, $maxLength]) {
            if (
                !is_string($value)
                || trim($value) === ''
                || mb_strlen(trim($value)) > $maxLength
            ) {
                $io->error(sprintf(
                    'Le champ "%s" est invalide.',
                    $label
                ));

                return Command::FAILURE;
            }
        }

        // Mot de passe (saisie masquée)
        $password = $io->askHidden('Mot de passe');
        $passwordConfirmation = $io->askHidden(
            'Confirmer le mot de passe'
        );

        if ($password !== $passwordConfirmation) {
            $io->error('Les mots de passe ne correspondent pas.');

            return Command::FAILURE;
        }

        // Vérifier la longueur du mot de passe.
        if (
            !is_string($password)
            || mb_strlen($password) < 12
            || mb_strlen($password) > 4096
        ) {
            $io->error(
                'Le mot de passe doit contenir entre 12 et 4096 caractères.'
            );

            return Command::FAILURE;
        }

        // Vérifier que le mot de passe n'a pas été compromis.
        $violations = $this->validator->validate(
            $password,
            new NotCompromisedPassword([
                'message' => 'Ce mot de passe a été compromis. Veuillez en choisir un autre.',
            ])
        );

        if (count($violations) > 0) {
            $io->error($violations[0]->getMessage());

            return Command::FAILURE;
        }

        // Créer le nouvel utilisateur.
        $user = new User();

        $user
            ->setEmail(trim($email))
            ->setNom(trim($nom))
            ->setPrenom(trim($prenom))
            ->setAdresse(trim($adresse))
            ->setVille(trim($ville))
            ->setCodePostal(trim($codePostal));

        // Attribuer le rôle administrateur.
        $user->setRoles(['ROLE_ADMIN']);

        // Hasher le mot de passe.
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $password
        );

        $user->setPassword($hashedPassword);

        // Enregistrer l'utilisateur en base de données.
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Le compte administrateur a été créé avec succès.');

        return Command::SUCCESS;
    }
}
