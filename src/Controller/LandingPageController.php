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
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
    public function index(Request $request, EntityManagerInterface $entityManager, CartRepository $cartRepository, \Twig\Environment $twig)
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

            // Récupérez l'ID de commande de l'API
            $orderIdApi = $content['order_id'];

            // Récupérez l'entité Cart correspondante depuis la base de données


            // Mettez à jour la valeur de l'ID de commande de l'API dans l'entité Cart
            $cart->setOrderIdApi($orderIdApi);

            $cartRepository->save($cart, true);



            // Payement Stripe


            // Récupération de la clé Stripe
            Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

            // création d'une nouvelle session de paiement avec la méthode statique create de la classe Session
            $session = Session::create([
                'payment_method_types' => ['card'], // spécifie les types de paiement acceptés ( ici, seulement la carte)
                'line_items' => [ // représente les éléments de la commande ( prix, devise, nom produit, quantité)
                    [
                        'price_data' => [
                            'currency' => 'eur',
                            'unit_amount' => $cart->getProduct()->getSale() * 100,
                            'product_data' => [
                                'name' => $cart->getProduct()->getName(),
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment', // paiement en temps réel

                // URL de redirection :
                'success_url' => $this->generateUrl('confirmation', ['id' => $cart->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                'cancel_url' => $this->generateUrl('landing_page', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

            // dd($cart);


            // Redirigez le client vers la page de paiement Stripe
            return $this->redirect($session->url);
        }
        // génère une réponse HTML et passe des variables à Twig pour le rendu
        return $this->render('landing_page/index_new.html.twig', [
            'form' => $form->createView(),
            'allproduct' => $allproduct,
        ]);



        // Refaire une requete a l'api pour actualiser le status de payement



        // $this->redirectToRoute('confirmation');


        // return $this->render('landing_page/index_new.html.twig', ['form' => $form, 'allproduct' => $allproduct]);

    }

    public function apiUpdatePaymentStatus($apiCommandId, $status)
    {
        $statusJson = ['status' => 'PAID'];

        $response2 = $this->client->request(
            'POST',
            'https://api-commerce.simplon-roanne.com/order/' . $apiCommandId . '/status',

            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'accept' => 'application/json',
                    'Authorization' => 'Bearer mJxTXVXMfRzLg6ZdhUhM4F6Eutcm1ZiPk4fNmvBMxyNR4ciRsc8v0hOmlzA0vTaX',
                ],
                'json' => $statusJson,
            ]
        );
    }



    /**
     * @Route("/confirmation/{id}", name="confirmation")
     */
    public function confirmation(Cart $cart, Request $request, CartRepository $cartRepository)
    {
        // dd($cart);
        // mettre a jour l'entité cart
        // Si l'objet $cart est une instance de la classe Commandes, le statut est mis à jour dans la BDD
        $cart->setStatus('PAID');
        $cartRepository->save($cart, true);
        // Redirection sur la méthode de modification du statut de paiement dans l'API grâce à l'ID de l'API
        $this->apiUpdatePaymentStatus($cart->getOrderIdApi(), 'PAID');
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
