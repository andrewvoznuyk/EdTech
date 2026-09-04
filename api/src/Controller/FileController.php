<?php

namespace App\Controller;

use App\Action\ActionCreateFile;
use App\Entity\File;
use App\Enum\FileStatus;
use App\Service\FileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class FileController extends AbstractController
{
    /**
     * @param FileService $fileService
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly FileService            $fileService,
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer
    )
    {}

    /**
     * @param UploadedFile $uploadedFile
     * @param ActionCreateFile $createFileAction
     * @return JsonResponse
     * @throws ExceptionInterface
     */
    #[Route('/api/file/upload', name: 'upload', methods: ['POST'])]
    #[IsGranted("ROLE_USER")]
    public function upload(#[MapUploadedFile] UploadedFile $uploadedFile, ActionCreateFile $createFileAction): JsonResponse
    {
        $file = $createFileAction($uploadedFile, $this->getUser());

        $result = $this->fileService->uploadUploadedFile($uploadedFile, $file);

        $file->setStatus(FileStatus::UPLOADED)->setMetadata($result->toArray());
        $this->entityManager->flush();

        return new JsonResponse([
            $this->serializer->normalize($file, 'json', [AbstractNormalizer::GROUPS => ['get:item:file']]),
            'downloadUrl' => $this->fileService->generatePresignedDownloadUrl($file)
        ], Response::HTTP_CREATED);
    }

    /**
     * @param File $file
     * @return JsonResponse
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/api/file/download/{fileKey}', name: 'download-file', methods: ['GET'])]
    public function download(#[MapEntity(mapping: ['fileKey' => 'fileKey'])] File $file): JsonResponse
    {
        return new JsonResponse([$this->fileService->generatePresignedDownloadUrl($file)], Response::HTTP_OK);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/api/file/download-batch', name: 'download-collection-files-batch', methods: ['GET'])]
    public function downloadCollectionBatch(Request $request): JsonResponse
    {
        $fileKeys = $request->query->all('fileKeys');

        if (empty($fileKeys)) {
            return new JsonResponse(['error' => 'Відсутні параметри fileKeys в URL'], Response::HTTP_BAD_REQUEST);
        }

        $files = $this->entityManager->getRepository(File::class)->findBy(['fileKey' => $fileKeys]);

        $urls = $this->fileService->generatePresignedDownloadUrls($files);

        return new JsonResponse($urls, Response::HTTP_OK);
    }
}
