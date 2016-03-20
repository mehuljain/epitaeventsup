<?php

namespace Events\Bundle\EventsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Events\Bundle\EventsBundle\Entity\User;
use Events\Bundle\EventsBundle\Form\Type\UserType;
use Symfony\Component\HttpFoundation\Request;
use Events\Bundle\EventsBundle\Entity\Subscribed;
use Events\Bundle\EventsBundle\Form\Type\EventoneType;
use Events\Bundle\EventsBundle\Form\Type\EventtwoType;
use Events\Bundle\EventsBundle\Form\Type\EventthreeType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Filesystem\Filesystem;

class DefaultController extends Controller {

    /**
     * @Route("/")
     * @Template()
     */
    public function indexAction() {

        if ($this->get('security.context')->isGranted('ROLE_USER')) {
            return $this->redirect($this->generateUrl('securedhome'));
        }
        return array();
    }

    /**
     * @Route("/closepage",name="closepage")
     * @Template()
     */
    public function closeAction() {

        return array();
    }

    /**
     * @Route("/register",name="register")
     * @Template()
     */
    public function registerAction(Request $request) {

//        return new RedirectResponse($this->generateUrl('closepage'));

        $em = $this->getDoctrine()->getManager();
        //Check to see if the user has already logged in
        if ($this->get('security.context')->isGranted('ROLE_USER')) {
            return $this->redirect($this->generateUrl('securedhome'));
        }

        $user = new User();

        $form = $this->createForm(new UserType(), $user);
        $form->handleRequest($request);
        if ($form->isValid()) {
            //Do the needful
            $date = new \DateTime();
            $user->setCreatedon($date);
            $user->setEnabled(TRUE);
            $em->persist($user);
            $em->flush();
            $this->authenticateUser($user);
            $route = 'securedhome';
            $url = $this->generateUrl($route);
            return $this->redirect($url);
        }

        return array('form' => $form->createView());
    }

    /**
     * @Route("/secured/home",name="securedhome")
     * @Template()
     */
    public function homeAction(Request $request) {

//        return new RedirectResponse($this->generateUrl('closepage'));

        $em = $this->getDoctrine()->getManager();

        if (!$this->get('security.context')->isGranted('ROLE_USER')) {
            return $this->redirect($this->generateUrl('events_events_default_index'));
        }
        $user = $em->getRepository('EventsEventsBundle:User')->find($this->get('security.context')->getToken()->getUser()->getId());

        if (!is_object($user) || !$user instanceof User) {
            throw new \Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException('This user does not have access to this section.');
        }

        return array();
    }

    /**
     * @Route("/secured/eventone",name="eventone")
     * @Template()
     */
    public function eventoneAction(Request $request) {

        $exists = false;

        if (!$this->get('security.context')->isGranted('ROLE_USER')) {
            return $this->redirect($this->generateUrl('events_events_default_index'));
        }
        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository('EventsEventsBundle:User')->find($this->get('security.context')->getToken()->getUser()->getId());


        if (!is_object($user) || !$user instanceof User) {
            throw new \Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException('This user does not have access to this section.');
        }

        $subrecord = $em->getRepository('EventsEventsBundle:Subscribed')->findOneBy(array('user' => $user->getId()));

        if (!empty($subrecord)) {
            $exists = true;
            if ($subrecord->getEventtype1() != null || $subrecord->getEventtype1() != '') {
                $event1 = $subrecord->getEventtype1()->getId();
            } else {
                $event1 = '';
            }
            if (($subrecord->getEventtype2() != null || $subrecord->getEventtype2() != '')) {
                $event2 = $subrecord->getEventtype2()->getId();
            } else {
                $event2 = '';
            }
            if (($subrecord->getEventtype3() != null || $subrecord->getEventtype3() != '')) {
                $event3 = $subrecord->getEventtype3()->getId();
            } else {
                $event3 = '';
            }
        }

        $subscribed = new Subscribed();

        $form = $this->createForm(new EventoneType($subrecord), $subscribed);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            //First check the value entered by the user
            if ($subscribed->getEventtype1() == null ||
                    $subscribed->getEventtype2() == null ||
                    $subscribed->getEventtype3() == null
            ) {
                //User did not choose both the events
                $this->container->get('session')->getFlashBag()->add('error', 'Oh oh! It is mandatory to choose an option for all the events');
                return array('form' => $form->createView());
            }

            //Identical events should not be selected
            if (($subscribed->getEventtype2() == 3 && $subscribed->getEventtype3() == 10) ||
                    ($subscribed->getEventtype2() == 4 && $subscribed->getEventtype3() == 11) ||
                    ($subscribed->getEventtype2() == 5 && $subscribed->getEventtype3() == 12) ||
                    ($subscribed->getEventtype2() == 6 && $subscribed->getEventtype3() == 13) ||
                    ($subscribed->getEventtype2() == 7 && $subscribed->getEventtype3() == 14) ||
                    ($subscribed->getEventtype2() == 8 && $subscribed->getEventtype3() == 15) ||
                    ($subscribed->getEventtype2() == 9 && $subscribed->getEventtype3() == 16)
            ) {
                //User chose identical events
                $this->container->get('session')->getFlashBag()->add('error', 'Oh no! Not the same event twice. Please choose another event.');
                return array('form' => $form->createView());
            }


            $max = $this->container->getParameter('max_cultural');
            $maxfood = $this->container->getParameter('max_food');
            //Now check for the participants limit
            $qb1 = $em->createQueryBuilder();
            $qb1->select('count(subscribed.id)');
            $qb1->from('EventsEventsBundle:Subscribed', 'subscribed');
            $qb1->where('subscribed.eventtype1 = :bar');
            $qb1->setParameter('bar', $subscribed->getEventtype1());

            $total1 = $qb1->getQuery()->getSingleScalarResult();

            if ($exists) {
                if ($event1 != $subscribed->getEventtype1()) {
                    if ($total1 > $maxfood || $total1 == $maxfood) {
                        $this->container->get('session')->getFlashBag()->add('error', 'The registrations are full for this Food Event. Please choose another time slot for the Food Event');
                        return array('form' => $form->createView());
                    }
                }
            } else {
                if ($total1 > $maxfood || $total1 == $maxfood) {
                    $this->container->get('session')->getFlashBag()->add('error', 'The registrations are full for this Food Event. Please choose another time slot for the Food Event');
                    return array('form' => $form->createView());
                }
            }

            $qb2 = $em->createQueryBuilder();
            $qb2->select('count(subscribed.id)');
            $qb2->from('EventsEventsBundle:Subscribed', 'subscribed');
            $qb2->where('subscribed.eventtype2 = :bar');
            $qb2->setParameter('bar', $subscribed->getEventtype2());

            $total2 = $qb2->getQuery()->getSingleScalarResult();
            if ($exists) {
                if ($event2 != $subscribed->getEventtype2()) {
                    if ($total2 > $max || $total2 == $max) {
                        $this->container->get('session')->getFlashBag()->add('error', 'The registrations are full for the selected Cultural Event 1.Please choose another event');
                        return array('form' => $form->createView());
                    }
                }
            } else {
                if ($total2 > $max || $total2 == $max) {
                    $this->container->get('session')->getFlashBag()->add('error', 'The registrations are full for the selected Cultural Event 2.Please choose another event');
                    return array('form' => $form->createView());
                }
            }

            $qb3 = $em->createQueryBuilder();
            $qb3->select('count(subscribed.id)');
            $qb3->from('EventsEventsBundle:Subscribed', 'subscribed');
            $qb3->where('subscribed.eventtype3 = :bar');
            $qb3->setParameter('bar', $subscribed->getEventtype3());

            $total3 = $qb3->getQuery()->getSingleScalarResult();

            if ($exists) {
                if ($event3 != $subscribed->getEventtype3()) {
                    if ($total3 > $max || $total3 == $max) {
                        $this->container->get('session')->getFlashBag()->add('error', 'The registrations are full for the selected Cultural Event 2.Please choose another event');
                        return array('form' => $form->createView());
                    }
                }
            } else {
                if ($total3 > $max || $total3 == $max) {
                    $this->container->get('session')->getFlashBag()->add('error', 'The registrations are full for the selected Cultural Event 2.Please choose another event');
                    return array('form' => $form->createView());
                }
            }
        }


        if ($form->isValid()) {

            $sub = $em->getRepository('EventsEventsBundle:Subscribed')->findOneBy(array('user' => $user->getId()));
            $eventtype1 = $em->getRepository('EventsEventsBundle:Eventtype')->findOneBy(array('id' => $subscribed->getEventtype1()));
            $eventtype2 = $em->getRepository('EventsEventsBundle:Eventtype')->findOneBy(array('id' => $subscribed->getEventtype2()));
            $eventtype3 = $em->getRepository('EventsEventsBundle:Eventtype')->findOneBy(array('id' => $subscribed->getEventtype3()));

            if (empty($sub)) {
                $subscribed->setUser($user);
                $subscribed->setEventtype1($eventtype1);
                $subscribed->setEventtype2($eventtype2);
                $subscribed->setEventtype3($eventtype3);
                $em->persist($subscribed);
                $copy = $subscribed;
            } else {
                $sub->setEventtype1($eventtype1);
                $sub->setEventtype2($eventtype2);
                $sub->setEventtype3($eventtype3);
                $em->persist($sub);
                $copy = $sub;
            }
            $em->flush();
            $route = 'securedhome';
            $url = $this->generateUrl($route);
            $this->container->get('session')->getFlashBag()->add('success', 'We have your registrations for the events on Wednesday. Thank you!');
            $message = \Swift_Message::newInstance()
                    ->setSubject('EPITA International - Your Registrations for Wednesday, 23rd March 2016')
                    ->setFrom('epitaevents2016@gmail.com')
                    ->setTo($user->getEmailCanonical())
                    ->setContentType("text/html")
                    ->setBody(
                    $this->renderView('EventsEventsBundle:Default:wednesdaymail.html.twig', array('row' => $copy)
                    ));
            $this->get('mailer')->send($message);
            return $this->redirect($url);
        }

        return array('form' => $form->createView());
    }

   


    /**
     * Authenticate the user
     * 
     * @param FOS\UserBundle\Model\UserInterface
     */
    protected function authenticateUser(User $user) {
        try {
            $this->container->get('security.user_checker')->checkPostAuth($user);
        } catch (AccountStatusException $e) {
            // Don't authenticate locked, disabled or expired users
            return;
        }

        $providerKey = $this->container->getParameter('fos_user.firewall_name');
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
        $this->container->get('security.context')->setToken($token);
    }

}