<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\Persistence\ObjectManager;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;

class UserCrudController extends AbstractCrudController
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('email'),
            TextField::new('password')->onlyOnForms(),
            TextField::new('firstName'),
            TextField::new('lastName'),
        ];
    }

    public function persistEntity(ObjectManager $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            if ($entityInstance->getPassword()) {
                $entityInstance->setPassword(
                    $this->passwordHasher->hashPassword($entityInstance, $entityInstance->getPassword())
                );
            }
            $entityInstance->setRoles(['ROLE_USER']);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(ObjectManager $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            if ($entityInstance->getPassword()) {
                $entityInstance->setPassword(
                    $this->passwordHasher->hashPassword($entityInstance, $entityInstance->getPassword())
                );
            }
            $entityInstance->setRoles(['ROLE_USER']);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }
}
