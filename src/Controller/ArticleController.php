<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/artikel')]
final class ArticleController extends AbstractController
{
    #[Route('', name: 'article_list', methods: ['GET'])]
    public function list(ArticleRepository $repo): Response
    {
        return $this->render('article/list.html.twig', [
            'articles' => $repo->findBy([], ['sku' => 'ASC']),
        ]);
    }

    #[Route('/neu', name: 'article_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $article = new Article();
        return $this->handle($request, $em, $article, 'Artikel angelegt.');
    }

    #[Route('/{id}', name: 'article_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $em, Article $article): Response
    {
        return $this->handle($request, $em, $article, 'Artikel aktualisiert.');
    }

    #[Route('/{id}/loeschen', name: 'article_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em, Article $article): Response
    {
        if (!$this->isCsrfTokenValid('article-delete-'.$article->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($article);
        $em->flush();
        $this->addFlash('success', 'Artikel gelöscht.');
        return $this->redirectToRoute('article_list');
    }

    private function handle(Request $request, EntityManagerInterface $em, Article $article, string $flash): Response
    {
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($article);
            $em->flush();
            $this->addFlash('success', $flash);
            return $this->redirectToRoute('article_list');
        }
        return $this->render('article/edit.html.twig', [
            'form' => $form,
            'article' => $article,
        ]);
    }
}
