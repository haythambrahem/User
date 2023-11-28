<?php

namespace App\Controller;

use App\Entity\Personne;
use App\Form\PersonneType;
use App\Form\PersonneEditType;
use App\Form\ChangePasswordType;
use App\Form\SearchPersonneType;
use App\Repository\PersonneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use DateTime;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Contracts\Translation\TranslatorInterface;
/// Mail 
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Symfony\Component\Mailer\MailerInterface;
use App\Security\EmailVerifier;
use Symfony\Component\Form\FormError;


#[Route('/personne')]
class PersonneController extends AbstractController
{
    private EmailVerifier $emailVerifier;
    private $verifyEmailHelper;
    private $mailer;

    public function __construct(EmailVerifier $emailVerifier, VerifyEmailHelperInterface $helper, MailerInterface $mailer)
    {
        $this->emailVerifier = $emailVerifier;
        $this->verifyEmailHelper = $helper;
        $this->mailer = $mailer;
    }

    #[Route('/stats', name: 'stats', methods:['GET', 'POST'])]
    public function stats(PersonneRepository $personneRepository): Response
    {
        $bannedUsers = $personneRepository->countBannedUsers();
        $totalUsers = $personneRepository->countTotalUsers();
        $verifiedUsers = $personneRepository->countVerifiedUsers();

        return $this->render('personne/stats.html.twig', [
            'bannedUsers' => $bannedUsers,
            'activeUsers' => $totalUsers - $bannedUsers,
            'verifiedUsers' => $verifiedUsers,
            'nonVerifiedUsers' => $totalUsers - $verifiedUsers,
        ]);
    }


   

    #[Route('/register', name: 'register', methods:['GET', 'POST'])]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher,EntityManagerInterface $entityManager): Response
    {
        
        $user = new Personne();
        $form = $this->createForm(PersonneType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {


            /** @var UploadedFile $file */
            $file = $form->get('pprofile')->getData();

            // If a file was uploaded
            if ($file) {
                $filename = uniqid() . '.' . $file->guessExtension();

                // Move the file to the directory where brochures are stored
                $file->move(
                    'userImages',
                    $filename
                );

                // Update the 'image' property to store the image file name
                // instead of its contents
                $user->setPprofile($filename);
            }
            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            ///////
            $signatureComponents = $this->verifyEmailHelper->generateSignature(
                'app_verify_email',
                $user->getId(),
                $user->getEmail()
            );
            $email = new TemplatedEmail();
            $email->subject('Veuillez confirmer votre email');
            $email->from('haythambrahem@gmail.com');
            $email->to($user->getEmail());
            $email->htmlTemplate('email/confirmation_email.html.twig');
            $email->context(['signedUrl' => $signatureComponents->getSignedUrl(),
             
                'nom' => $user->getNom(),
                ]);
            //$email->context(['nom' => $user->getNom()]);
            $this->mailer->send($email);

            //////  
            
            //$logger->debug('The signed URL is: ' . $signatureComponents->getSignedUrl());
            //dump($logger);
            $entityManager->persist($user);
            $entityManager->flush();
            return $this->redirectToRoute('app_login');
        }
        return $this->render('personne/register.html.twig'
        , [
         
            'form' => $form->createView(),
        ]);
    }
    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET', 'POST'])]
    public function verifyUserEmail(Request $request, PersonneRepository $personneRepository, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        
        $user->setIsVerified(true);
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $this->redirectToRoute('front_personne');
        
    }

    #[Route('/front', name: 'front_personne', methods: ['GET'])]
    public function frontperso(): Response
    {
        return $this->render('client.html.twig');
    }

    #[Route('/profile', name: 'app_personne_profile', methods: ['GET'])]
    public function profile(PersonneRepository $personneRepository): Response
    {
        $randomUsers = $personneRepository->findLatestUsers();

        return $this->render('login/profile.html.twig', [
            'randomUsers' => $randomUsers,
        ]);
    }

    #[Route('/edit/{id}', name: 'app_personne_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Personne $personne, PersonneRepository $personneRepository): Response
    {
        
        $form = $this->createForm(PersonneEditType::class, $personne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('pprofile')->getData()) {
                $file = $form->get('pprofile')->getData();

                // If a file was uploaded
                if ($file) {
                    $filename = uniqid() . '.' . $file->guessExtension();

                    // Move the file to the directory where brochures are stored
                    $file->move(
                        'userImages',
                        $filename
                    );

                    // Update the 'image' property to store the image file name
                    // instead of its contents
                    $personne->setPprofile($filename);
                }
                

   
            } else {
                // Keep the old profile picture
                $personne->setPprofile($personne->getPprofile());
            }
            $personne->setEmail($personne->getEmail());

            $personneRepository->save($personne, true);

            return $this->redirectToRoute('app_personne_profile', ['id' => $personne->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('personne/edit.html.twig', [
            'user' => $personne,
            'form' => $form,
        ]);
    }
   /* #[Route('/changepassword/{id}', name: 'app_personne_passchange', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $userPasswordHasher, PersonneRepository $personneRepository, Personne $personne)
    {
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $oldPassword = $form->get('oldPassword')->getData();

            if ($userPasswordHasher->isPasswordValid($personne, $oldPassword)) {
                $personne->setPassword(
                    $userPasswordHasher->hashPassword(
                        $personne,
                        $form->get('newPassword')->getData()
                    )
                );
                $personneRepository->save($personne, true);
                $this->addFlash('success', 'Your password has been changed.');
                return $this->render('personne/profile.html.twig');
            } else {
                $form->get('oldPassword')->addError(new FormError('Mot de passe invalide.'));
            }
        }
        return $this->renderForm('personne/passmodify.html.twig', [
            'user' => $personne,
            'form' => $form,
        ]);
    }*/

    #[Route('/banned', name: 'app_banned', methods: ['GET'])]
    public function banned(): Response
    {
        //TO DO : FILL THIS
        //$users = $personneRepository->findAllUsers();

        return $this->render('404notfound.html.twig');
    }

     #[Route('/backend', name: 'personne_back', methods: ['GET', 'POST'])]
     public function backend(EntityManagerInterface $entityManager, Request $request, Request $request2): Response
     {
         $form = $this->createForm(SearchPersonneType::class);
         $search = $form->handleRequest($request2);
         $em = $this->getDoctrine()->getManager();
         //////////////////////////
     $query = $entityManager->getRepository(Personne::class)->createQueryBuilder('u')->orderBy('u.id', 'ASC')->getQuery();
    
     $personnes = new Paginator($query);

     $currentPage = $request->query->getInt('page', 1);
     $itemsPerPage = 5;
     $firstResult = $itemsPerPage * ($currentPage - 1);
    
     $personnes
         ->getQuery()
         ->setFirstResult($firstResult)
         ->setMaxResults($itemsPerPage);

     $totalItems = count($personnes);
     $pagesCount = ceil($totalItems / $itemsPerPage);

     return $this->render('personne/index.html.twig', [
         'personnes' => $personnes,
         'currentPage' => $currentPage,
         'pagesCount' => $pagesCount,
         'form' => $form->createView(),
     ]);
 }

// #[Route('/backend', name: 'personne_back', methods: ['GET', 'POST'])]
// public function backend(EntityManagerInterface $entityManager, Request $request, Request $request2, PersonneRepository $personneRepository): Response
// {
//     $form = $this->createForm(SearchPersonneType::class);
//     $search = $form->handleRequest($request2);

//     // Handle search query
//     if ($form->isSubmitted() && $form->isValid()) {
//         $searchCriteria = $search->get('mots')->getData();
//         $personnes = $personneRepository->search($searchCriteria);
//         $personnes = new Paginator($personnes);
//     } else {
//         // Fetch all personnes
//         $query = $personneRepository->createQueryBuilder('u')->orderBy('u.id', 'ASC')->getQuery();
//         $personnes = new Paginator($query);
//     }

//     $currentPage = $request->query->getInt('page', 1);
//     $itemsPerPage = 5;
//     $personnes
//         ->getQuery()
//         ->setFirstResult($itemsPerPage * ($currentPage - 1))
//         ->setMaxResults($itemsPerPage);

//     $totalItems = count($personnes);
//     $pagesCount = ceil($totalItems / $itemsPerPage);

//     return $this->render('personne/index.html.twig', [
//         'personnes' => $personnes,
//         'currentPage' => $currentPage,
//         'pagesCount' => $pagesCount,
//         'form' => $form->createView(),
//     ]);
// }



   
    #[Route('/ban/{id}', name: 'personne_ban', methods: ['GET', 'POST'])]
    public function toggleBanUser($id): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $PersonneRepository = $entityManager->getRepository(Personne::class);
        $personne = $PersonneRepository->find($id);
        if (!$personne) {
            // Handle user not found, you might want to redirect or show an error message
            // For example, redirect to the index page
            return $this->redirectToRoute('register');
        }
        $personne->setIsBanned(!$personne->isIsBanned());
        $entityManager->flush();
        return $this->redirectToRoute('personne_back');
    }

    
}
