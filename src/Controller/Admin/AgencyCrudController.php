<?php

namespace App\Controller\Admin;

use App\Entity\Agency;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use Symfony\Component\Validator\Constraints\Date;

class AgencyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Agency::class;
    }
    public function configureFields(string $pageName): iterable
    {
        return [
                IdField::new('id')->hideOnForm()->hideOnIndex()->hideOnDetail(),
                TextField::new('code'),
                TextField::new('name'),
                BooleanField::new('active'),
                TextField::new('timezone')->hideOnForm()->hideOnIndex()->hideOnDetail(),
                DateTimeField::new('createdAt')->hideOnForm()->hideOnIndex()->hideOnDetail(),
        ];
    }
}
