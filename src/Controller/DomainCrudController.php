<?php

namespace App\Controller;

use App\Entity\Domain;
use App\Entity\HttpStrictTransportSecurity;
use App\Service\OsFunctionsService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,
    BooleanField,
    ChoiceField,
    CodeEditorField,
    IdField,
    TextField};

class DomainCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Domain::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['fqdn'])
            ->setDefaultSort(['fqdn' => 'ASC'])
            ->setAutofocusSearch();
    }

    public function configureFields(string $pageName): iterable
    {
        $osFunctions = new OsFunctionsService();
        $phpVersionsInstalled = $osFunctions->getPhpVersionsInstalled();
        foreach ($phpVersionsInstalled as $version) {
            $phpVersions['PHP ' . $version] = $version;
        }

        return [
            IdField::new('id')
                ->onlyOnDetail(),
            TextField::new('fqdn'),
            TextField::new('webroot')
                ->setEmptyData('public_html'),
            AssociationField::new('ip_addresses', 'IP Address'),
            AssociationField::new('owner', 'User'),
            ChoiceField::new('php_version')
                ->setChoices($phpVersions)
                ->setHelp('PHP version for this domain.'),
            AssociationField::new('ssl_cert', 'SSL Certificate'),
            BooleanField::new('redirect_to_ssl', 'Always redirect to SSL'),
            CodeEditorField::new('custom_config')
                ->hideOnIndex()
                ->setHelp('Text to be added directly to Apache Virtualhost section - no SSL host'),
            CodeEditorField::new('custom_config_ssl')
                ->hideOnIndex()
                ->setHelp('Text to be added directly to Apache Virtualhost section - SSL host'),
            ChoiceField::new('HttpStrictTransportSecurity', 'Strict Transport Security')
                ->setChoices(HttpStrictTransportSecurity::cases()),
            BooleanField::new('generateMtaSts', 'Generate MTA-STS')
                ->setHelp('Generate MTA-STS vhost for this domain - if MX and TXT records are set correctly and no dedicated vhost already declared.'),
            BooleanField::new('haveGenerateMtaSts', 'MTA-STS generated')
                ->setHelp('MTA-STS vhost is generated for this domain.')
                ->hideOnForm()
                ->setFormTypeOption('disabled', 'disabled'),
            BooleanField::new('is_active'),
            AssociationField::new('domainAliases', 'Aliases')
                ->onlyOnDetail(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
