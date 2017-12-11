<?php

namespace App\Controller;

use App\Entity\Image;
use App\Service\FileManager;
use App\Service\ImageResizer;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImageController extends Controller
{
    /** @var EntityManagerInterface */
    private $em;

    /** @var  FileManager */
    private $fileManager;

    /** @var  ImageResizer */
    private $imageResizer;

    public function __construct(EntityManagerInterface $em, FileManager $fileManager, ImageResizer $imageResizer)
    {
        $this->em = $em;
        $this->fileManager = $fileManager;
        $this->imageResizer = $imageResizer;
    }

    /**
     * @Route("/image/{id}/raw", name="image.serve")
     */
    public function serveImageAction(Request $request, $id)
    {
        $image = $this->em->getRepository(Image::class)->find($id);
        if (empty($image)) {
            throw new NotFoundHttpException('Image not found');
        }

        return $this->renderRawImage($image);
    }

    private function buildImageResponse(string $path, string $filename, int $cacheTtl)
    {
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-type', mime_content_type($path));
        $response->headers->set(
            'Content-Disposition',
            'inline; filename="' . $filename . '";'
        );

        $response->setTtl($cacheTtl);

        if ($cacheTtl === -1) {
            // Prevent caching
            $response->setPrivate();
            $response->setMaxAge(0);
            $response->headers->addCacheControlDirective('s-maxage', 0);
            $response->headers->addCacheControlDirective('must-revalidate', true);
            $response->headers->addCacheControlDirective('no-store', true);
            $response->setExpires(new \DateTime('-1 year'));
        } else {
            $response->setTtl($cacheTtl);
            $response->headers->addCacheControlDirective('must-revalidate', true);
        }

        return $response;
    }

    private function renderRawImage(Image $image)
    {
        $fullPath = $this->fileManager->getFilePath($image->getFilename());

        return $this->buildImageResponse($fullPath, $image->getOriginalFilename(), 1209600);
    }

}
