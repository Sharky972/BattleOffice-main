<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\Product;
use App\Entity\Cart;
use App\Form\CartType;
use App\Form\ProductType;
use App\Form\UserFormType;
use App\Repository\CartRepository;
use Symfony\Component\Form\SubmitButton;
use App\Service\GuzzleClient;
use GuzzleHttp\Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LandingPageController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $client,
    ) {
    }
    /**
     * @Route("/", name="landing_page")
     * @throws \Exception
     */
    public function index(Request $request, EntityManagerInterface $entityManager, CartRepository $cartRepository)
    {
        $bearer = $_ENV['BEARER_API_KEY'];


        $allproduct = $entityManager->getRepository(Product::class)->findAll();

        $cart = new Cart();
        $form = $this->createForm(CartType::class, $cart);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cartRepository->save($cart, true);



            $json = [
                'order' => [
                    'id'   => $cart->getId(),
                    'product' => $cart->getProduct()->getName(),
                    'payment_method' => 'Stripe',
                    'status' => 'WAITING',
                    'client' => [
                        "firstname" => $cart->getUser()->getFirstName(),
                        "lastname" => $cart->getUser()->getLastName(),
                        "email" => $cart->getUser()->getEmail()
                    ],

                    'addresses' => [
                        "billing" => [
                            "address_line1" => $cart->getUser()->getAdress(),
                            "address_line2" => $cart->getUser()->getAdress(),
                            "city" => $cart->getUser()->getCity(),
                            "zipcode" => $cart->getUser()->getPosteCode(),
                            "country" => $cart->getUser()->getCountry(),
                            "phone" => strval($cart->getUser()->getPhone()),
                        ],

                        "shipping" => [
                            "address_line1" => $cart->getUser()->getAdress(),
                            "address_line2" => $cart->getUser()->getAdress(),
                            "city" => $cart->getUser()->getCity(),
                            "zipcode" => $cart->getUser()->getPosteCode(),
                            "country" => $cart->getUser()->getCountry(),
                            "phone" => strval($cart->getUser()->getPhone()),

                        ]
                    ]
                ]
            ];

            try {
                $response = $this->client->request(
                    'POST',
                    'https://api-commerce.simplon-roanne.com/order',
                    [
                        'headers' => [
                            'Authorization' => $bearer
                        ],
                        'json' => $json
                    ]
                );
            } catch (\Throwable $th) {
                //throw $th;
                // $this->addFlash()
                $this->redirectToRoute('landing_page');
            }

            // $statusCode = $response->getStatusCode();
            $content = $response->toArray();
            // dd($content);
            // Récupérez l'ID de commande de l'API
            $orderIdApi = $content['order_id'];

            // Récupérez l'entité Cart correspondante depuis la base de données


            // Mettez à jour la valeur de l'ID de commande de l'API dans l'entité Cart
            $cart->setOrderIdApi($orderIdApi);

            $cartRepository->save($cart, true);



            // Payement Stripe

            // mettre a jour l'entité cart

            // Refaire une requete a l'api pour actualiser le status de payement


            $this->redirectToRoute('confirmation');
        }


        return $this->render('landing_page/index_new.html.twig', ['form' => $form, 'allproduct' => $allproduct]);
    }


    /**
     * @Route("/confirmation", name="confirmation")
     */
    public function confirmation()
    {
        // send email confirmation
        return $this->render('landing_page/confirmation.html.twig', []);
    }


    /**
     * @Route("/submit", name="app_submit")
     */
    public function submit(EntityManagerInterface $entityManager)
    {
        return $this->redirectToRoute('app_home');
    }
}
