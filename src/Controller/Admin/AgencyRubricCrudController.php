<?php

namespace App\Controller\Admin;

use App\Entity\AgencyRubric;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use Symfony\Component\Validator\Constraints\Date;

class AgencyRubricCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AgencyRubric::class;
    }
    
    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('code'),
            TextField::new('name'),
            TextField::new('category'),
            AssociationField::new('agency'),
            DateTimeField::new('createdAt')->hideOnForm()->hideOnIndex(),
            DateTimeField::new('updatedAt')->hideOnForm()->hideOnIndex(),
        ];
    }
}