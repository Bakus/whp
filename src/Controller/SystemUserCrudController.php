<?php

namespace App\Controller;

use App\Entity\SystemUser;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Crud, KeyValueStore};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\{ChoiceField, Field, FormField, TextField};
use Symfony\Component\Form\{FormBuilderInterface, FormEvents};
use Symfony\Component\Form\Extension\Core\Type\{PasswordType, RepeatedType};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SystemUserCrudController extends AbstractCrudController
{
    public function __construct(
        protected UserPasswordHasherInterface $userPasswordHasher
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return SystemUser::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['username'])
            ->setDefaultSort(['username' => 'ASC'])
            ->setAutofocusSearch();
    }

    public function configureFields(string $pageName): iterable
    {
        $roles = ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER'];
        return [
            TextField::new('username'),
            ChoiceField::new('roles')
                ->setChoices(array_combine($roles, $roles))
                ->allowMultipleChoices()
                ->renderAsBadges(),
            FormField::addPanel('Change password')
                ->onlyWhenCreating()
                ->setIcon('fa fa-key'),
            Field::new('password', 'New password')
                ->onlyWhenCreating()->setRequired(true)
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'type' => PasswordType::class,
                    'first_options' => ['label' => 'New password'],
                    'second_options' => ['label' => 'Repeat password'],
                    'error_bubbling' => true,
                    'invalid_message' => 'The password fields do not match.',
                ]),
            Field::new('password', 'New password')
                ->onlyWhenUpdating()->setRequired(false)->setEmptyData('')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'type' => PasswordType::class,
                    'first_options' => ['label' => 'New password'],
                    'second_options' => ['label' => 'Repeat password'],
                    'error_bubbling' => true,
                    'invalid_message' => 'The password fields do not match.',
                ]),
        ];
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

    private function hashPassword()
    {
        return function ($event) {
            $form = $event->getForm();
            if (!$form->isValid()) {
                return;
            }

            $plainPassword = $form->get('password')->getData();
            if ($plainPassword === null || $plainPassword === '') {
                return;
            }

            $systemUser = $form->getData();
            $hash = $this->userPasswordHasher->hashPassword($systemUser, $plainPassword);
            $form->getData()->setPassword($hash);
        };
    }
}
