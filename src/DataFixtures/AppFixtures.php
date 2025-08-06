<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $om): void
    {
        // Créez l'utilisateur de démonstration
        $user = new User();
        $user->setEmail('demo@demo.com');
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'demo'));
        $user->setUsername('demo');
        $user->setVerified(true);
        $om->persist($user);

        $om->flush();
    }
}
