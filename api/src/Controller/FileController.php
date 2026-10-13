<?php

namespace App\Controller;

use App\Entity\File;
use App\Enum\FileStatus;
use App\Service\EntityService\File\FileServiceInterface;
use App\Service\File\StorageServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
     * @param StorageServiceInterface $storageService
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @param FileServiceInterface $fileService
     */
    public function __construct(
        private readonly StorageServiceInterface $storageService,
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly FileServiceInterface $fileService,
    ) {}

    /**
     * @param UploadedFile $uploadedFile
     * @return JsonResponse
     * @throws ExceptionInterface
     */
    #[Route('/api/file/upload', name: 'upload', methods: ['POST'])]
    #[IsGranted("ROLE_USER")]
    public function upload(#[MapUploadedFile] UploadedFile $uploadedFile): JsonResponse
    {
        $file = $this->fileService->createFromFile($uploadedFile, $this->getUser());

        $result = $this->storageService->upload($uploadedFile, $file);

        $file->setStatus(FileStatus::UPLOADED)->setMetadata($result->toArray());
        $this->entityManager->flush();

        return new JsonResponse([
            $this->serializer->normalize($file, 'json', [AbstractNormalizer::GROUPS => ['get:item:file']]),
            'downloadUrl' => $this->storageService->generatePresignedDownloadUrl($file)
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
        return new JsonResponse([$this->storageService->generatePresignedDownloadUrl($file)], Response::HTTP_OK);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/api/file/download-batch', name: 'download-collection-files-batch', methods: ['GET'])]
    public function downloadCollectionBatch(Request $request): JsonResponse
    {
        $fileKeys = $request->query->all('fileKeys');

        if (empty($fileKeys)) {
            return new JsonResponse(['errors' => ['fileKeys' => ['Відсутні параметри fileKeys в URL']]], Response::HTTP_BAD_REQUEST);
        }

        $files = $this->entityManager->getRepository(File::class)->findBy(['fileKey' => $fileKeys]);

        $urls = $this->storageService->generatePresignedDownloadUrls($files);

        return new JsonResponse($urls, Response::HTTP_OK);
    }
}
