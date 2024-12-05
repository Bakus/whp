<?php

namespace App\Controller;

use App\Entity\User;
use App\Message\ChmodReset;
use App\Message\ChownReset;

use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, KeyValueStore};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, BooleanField, IdField, TextField, IntegerField, CodeEditorField, DateTimeField};
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

use Symfony\Component\Form\{FormBuilderInterface, FormEvents};
use Symfony\Component\Form\Extension\Core\Type\{PasswordType, RepeatedType};
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['username'])
            ->setDefaultSort(['username' => 'ASC'])
            ->setAutofocusSearch()
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnDetail(),
            TextField::new('username'),
            AssociationField::new('parent_user'),
            TextField::new('password')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'type' => PasswordType::class,
                    'first_options' => ['label' => 'Password'],
                    'second_options' => ['label' => '(Repeat)'],
                    'mapped' => false,
                ])
                ->setRequired($pageName === Crud::PAGE_NEW)
                ->onlyOnForms(),
            IntegerField::new('uid'),
            TextField::new('home_dir'),
            BooleanField::new('is_active'),
            AssociationField::new('domains', 'Domains')
                ->onlyOnDetail(),
            CodeEditorField::new('phpFpmConfigExtra')
                ->hideOnIndex()
                ->setFormTypeOptions(['attr' => ['rows' => 10]])
                ->setHelp('Extra configuration for PHP-FPM pools'),
            IntegerField::new('FtpLoginCount')
                ->onlyOnDetail(),
            DateTimeField::new('FtpLastLogin')
                ->onlyOnDetail(),
            DateTimeField::new('FtpLastModified')
                ->onlyOnDetail(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // ->add(Crud::PAGE_INDEX, Action::new('resetChmod', 'Reset CHMOD')->linkToCrudAction('resetChmod'))
            // ->add(Crud::PAGE_INDEX, Action::new('resetChown', 'Reset CHOWN')->linkToCrudAction('resetChown'))
            ->add(Crud::PAGE_DETAIL, Action::new('resetChmod', 'Reset CHMOD', 'fa fa-user-lock')->linkToCrudAction('resetChmod'))
            ->add(Crud::PAGE_DETAIL, Action::new('resetChown', 'Reset CHOWN', 'fa fa-solid fa-people-arrows')->linkToCrudAction('resetChown'))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createNewFormBuilder($entityDto, $formOptions, $context);
        return $this->addPasswordEventListener($formBuilder);
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);
        return $this->addPasswordEventListener($formBuilder);
    }
    private function addPasswordEventListener(FormBuilderInterface $formBuilder): FormBuilderInterface
    {
        return $formBuilder->addEventListener(FormEvents::POST_SUBMIT, $this->hashPassword());
    }

    private function hashPassword() {
        return function($event) {
            $form = $event->getForm();
            if (!$form->isValid()) {
                return;
            }
            $password = $form->get('password')->getData();
            if ($password === null) {
                return;
            }

            $hash = "{md5}".base64_encode( pack( "H*", md5( $password ) ) );
            $form->getData()->setPassword($hash);
        };
    }

    public function resetChmod(AdminContext $context, MessageBusInterface $bus): RedirectResponse
    {
        $user = $context->getEntity()->getInstance();
        $bus->dispatch(new ChmodReset($user->getUsername()));
        $this->addFlash('success', 'CHMOD reset added to queue');

        $routeBuilder = $this->container->get(AdminUrlGenerator::class);
        $url = $routeBuilder->setController(UserCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($user->getId())
            ->generateUrl()
        ;

        return $this->redirect($url);
    }

    public function resetChown(AdminContext $context, MessageBusInterface $bus): RedirectResponse
    {
        $user = $context->getEntity()->getInstance();
        $bus->dispatch(new ChownReset($user->getUsername()));
        $this->addFlash('success', 'CHOWN reset added to queue');

        $routeBuilder = $this->container->get(AdminUrlGenerator::class);
        $url = $routeBuilder->setController(UserCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($user->getId())
            ->generateUrl()
        ;

        return $this->redirect($url);
    }
}
