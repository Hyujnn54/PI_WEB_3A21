<?php

namespace App\Controller;

use App\Service\Interview\InterviewMapLookupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class InterviewMapController extends AbstractController
{
    #[Route('/front/interviews/map-search', name: 'front_interview_map_search', methods: ['GET'])]
    public function search(Request $request, InterviewMapLookupService $lookupService): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if ($query === '') {
            return $this->json([
                'ok' => true,
                'results' => [],
            ]);
        }

        return $this->json([
            'ok' => true,
            'results' => $lookupService->search($query),
        ]);
    }

    #[Route('/front/interviews/map-reverse', name: 'front_interview_map_reverse', methods: ['GET'])]
    public function reverse(Request $request, InterviewMapLookupService $lookupService): JsonResponse
    {
        $lat = (float) $request->query->get('lat', 0);
        $lng = (float) $request->query->get('lng', 0);
        $place = $lookupService->reverse($lat, $lng);

        return $this->json([
            'ok' => $place !== null,
            'place' => $place,
        ]);
    }
}
