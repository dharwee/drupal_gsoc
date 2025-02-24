<?php

namespace Drupal\ai_media_proof\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Entity\Event\EntityEvents;
use Drupal\Core\Entity\Event\EntityInsertEvent;
use Drupal\Core\Entity\Event\EntityUpdateEvent;
use Drupal\media\Entity\Media;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MediaInsertSubscriber implements EventSubscriberInterface {
  protected $entityTypeManager;
  protected $httpClientFactory;
  protected $fileSystem;

 
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ClientFactory $httpClientFactory,
    FileSystemInterface $fileSystem
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->httpClientFactory = $httpClientFactory;
    $this->fileSystem = $fileSystem;
  }


  public static function getSubscribedEvents() {
    return [
      EntityEvents::INSERT => 'onEntityInsert',
      EntityEvents::UPDATE => 'onEntityUpdate',
    ];
  }


  public function onEntityInsert(EntityInsertEvent $event) {
    $entity = $event->getEntity();
    $this->processMediaEntity($entity, 'INSERT');
  }

 
  public function onEntityUpdate(EntityUpdateEvent $event) {
    $entity = $event->getEntity();
    $this->processMediaEntity($entity, 'UPDATE');
  }

 
  protected function processMediaEntity(EntityInterface $entity, $operation) {
    
    if (!$entity instanceof Media) {
      return;
    }

    \Drupal::logger('ai_media_proof')->notice(
      'Media entity @op with ID @id and bundle @bundle.',
      [
        '@op' => $operation,
        '@id' => $entity->id(),
        '@bundle' => $entity->bundle(),
      ]
    );

    if ($entity->bundle() === 'image') {
      \Drupal::logger('ai_media_proof')->notice('Media bundle is "image"; generating AI caption...');

    
      $caption = $this->generateCaptionFromAI($entity);

      if (!empty($caption)) {
        \Drupal::logger('ai_media_proof')->notice('AI caption generated: @caption', [
          '@caption' => $caption,
        ]);


        if ($entity->hasField('field_ai_caption')) {
          $entity->set('field_ai_caption', $caption);
          $entity->save();
          \Drupal::logger('ai_media_proof')->notice('AI caption saved to the media entity.');
        }
        else {
          \Drupal::logger('ai_media_proof')->warning(
            'Field "field_ai_caption" does not exist on this media entity.'
          );
        }
      }
      else {
        \Drupal::logger('ai_media_proof')->warning('No AI caption generated.');
      }
    }
    else {
      \Drupal::logger('ai_media_proof')->notice('Media bundle is not "image"; skipping caption generation.');
    }
  }

 
  protected function generateCaptionFromAI(Media $media) {
    
    $file = $media->get('field_media_image')->entity;
    if (!$file) {
      \Drupal::logger('ai_media_proof')->warning('No file found in media entity.');
      return '';
    }

  
    $realPath = $this->fileSystem->realpath($file->getFileUri());
    if (!file_exists($realPath)) {
      \Drupal::logger('ai_media_proof')->warning('File does not exist at path: @path', ['@path' => $realPath]);
      return '';
    }

    
    $imageData = file_get_contents($realPath);

    $mimeType = $file->getMimeType();
    \Drupal::logger('ai_media_proof')->notice('Processing file @path with MIME type @mime', [
      '@path' => $realPath,
      '@mime' => $mimeType,
    ]);

    try {
  
      $client = $this->httpClientFactory->fromOptions([
        'headers' => [
          'Authorization' => 'Bearer hf_xx1234',
          'Content-Type'  => $mimeType,
        ],
      ]);

    
      $url = 'https://api-inference.huggingface.co/models/nlpconnect/vit-gpt2-image-captioning?wait_for_model=true';

     
      $response = $client->post($url, [
        'body' => $imageData,
      ]);

     
      $responseBody = $response->getBody()->getContents();
      \Drupal::logger('ai_media_proof')->notice('API response: @response', ['@response' => $responseBody]);

      
      $data = json_decode($responseBody, TRUE);

      if (isset($data[0]['generated_text'])) {
        return $data[0]['generated_text'];
      }
      else {
        \Drupal::logger('ai_media_proof')->warning('No "generated_text" found in API response.');
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('ai_media_proof')->error('AI caption generation failed: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return '';
  }

}
