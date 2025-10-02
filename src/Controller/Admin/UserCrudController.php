<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\Persistence\ObjectManager;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
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

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setPageTitle('new', 'Créer un nouvel utilisateur')
            ->setPageTitle('edit', 'Modifier l\'utilisateur')
            ->setPageTitle('index', 'Liste des utilisateurs')
            ->setPageTitle('detail', 'Détails de l\'utilisateur');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('email', 'Adresse e-mail'),
            TextField::new('password', 'Mot de passe')->onlyOnForms(),
            TextField::new('firstName', 'Prénom'),
            TextField::new('lastName', 'Nom'),
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
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        
        $queryBuilder->andWhere('entity.email != :adminEmail')
                    ->setParameter('adminEmail', 'florent.devynck@groupevitaminet.com');
        
        return $queryBuilder;
    }
}
