<?php

namespace App\Form;

use App\Entity\Cart;
use App\Entity\Product;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', UserFormType::class)
            // ->add('product', ProductType::class);
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => 'name', // Remplacez 'name' par le nom de l'attribut affichant le nom de l'image dans votre entité Image
                'expanded' => true,
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cart::class,
        ]);
    }
}
