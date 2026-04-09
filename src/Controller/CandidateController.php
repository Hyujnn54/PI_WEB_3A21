<?php

namespace App\Controller;

use App\Entity\Candidate;
use App\Entity\Candidate_skill;
use App\Repository\Candidate_skillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CandidateController extends AbstractController
{
    #[Route('/candidate/home', name: 'candidate_home')]
    public function home(): Response
    {
        return $this->render('front/candidate_home.html.twig');
    }

    // ====================== SKILLS LIST + ADD ======================
    #[Route('/candidate/skills', name: 'app_candidate_skills', methods: ['GET', 'POST'])]
    public function index(Request $request, Candidate_skillRepository $skillRepo, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');

        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $skills = $skillRepo->findBy(['candidate' => $userId]);

        if ($request->isMethod('POST')) {
            $candidate = $em->getRepository(Candidate::class)->find($userId);

            $newSkill = new Candidate_skill();
            $newSkill->setSkillName($request->request->get('skill_name'));
            $newSkill->setLevel($request->request->get('level'));
            $newSkill->setCandidate($candidate);

            $em->persist($newSkill);
            $em->flush();

            $this->addFlash('success', 'Skill added successfully!');
            return $this->redirectToRoute('app_candidate_skills');
        }

        return $this->render('front/skills_page.html.twig', [
            'skills' => $skills
        ]);
    }

    // ====================== EDIT SKILL ======================
    #[Route('/candidate/skills/edit/{id}', name: 'app_candidate_skill_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Candidate_skill $skill, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');

        // Security: Make sure the skill belongs to the logged-in candidate
        if ($skill->getCandidate()->getId() !== $userId) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $skill->setSkillName($request->request->get('skill_name'));
            $skill->setLevel($request->request->get('level'));

            $em->flush();

            $this->addFlash('success', 'Skill updated successfully!');
            return $this->redirectToRoute('app_candidate_skills');
        }

        return $this->render('front/skills_edit.html.twig', [
            'skill' => $skill
        ]);
    }

// ====================== DELETE SKILL ======================
#[Route('/candidate/skills/delete/{id}', name: 'app_candidate_skill_delete')]
public function delete(
    Request $request,                    // ← Add this
    Candidate_skill $skill, 
    EntityManagerInterface $em
): Response
{
    $session = $request->getSession();
    $userId = $session->get('user_id');

    if (!$userId || $skill->getCandidate()->getId() !== $userId) {
        throw $this->createAccessDeniedException();
    }

    $em->remove($skill);
    $em->flush();

    $this->addFlash('success', 'Skill deleted successfully!');
    return $this->redirectToRoute('app_candidate_skills');
}
}