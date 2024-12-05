<?php

namespace App\Controller;

use App\Entity\DomainAlias;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, BooleanField, IdField, TextField};

class DomainAliasCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DomainAlias::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['domain_name'])
            ->setDefaultSort(['domain_name' => 'ASC'])
            ->setAutofocusSearch();
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnDetail(),
            AssociationField::new('domain_id'),
            TextField::new('domain_name'),
            BooleanField::new('is_active'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
