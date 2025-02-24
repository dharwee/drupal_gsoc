<?php

namespace Drupal\ai_media_proof\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\media\Entity\Media;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class MediaInsertSubscriber implements EventSubscriberInterface {

  protected $entityTypeManager;
  protected $httpClientFactory;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, ClientFactory $httpClientFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->httpClientFactory = $httpClientFactory;
  }
  function ai_media_proof_media_insert(Drupal\media\Entity\Media $media) {
    \Drupal::logger('media')->notice('Media inserted via hook for media ID: @id', ['@id' => $media->id()]);
  }
  
  public static function getSubscribedEvents() {
    $events['entity.insert'][] = ['onMediaInsert', -100];
    $events['entity.update'][] = ['onMediaInsert', -100];
    return $events;
  }
  

  public function onMediaInsert(GenericEvent $event): void {
    $entity = $event->getSubject();
    if (!$entity instanceof Media) {
      \Drupal::logger('media')->notice('The inserted entity is not media, skipping.');
      return;
    }

    \Drupal::logger('media')->notice('onMediaInsert triggered for media ID: @id', ['@id' => $entity->id()]);
    \Drupal::logger('media')->notice('Media bundle is: @bundle', ['@bundle' => $entity->bundle()]);

    if ($entity->bundle() === 'field_media_image') {
      \Drupal::logger('media')->notice('Media entity is an image, processing AI caption.');

      $caption = $this->generateCaptionFromAI($entity);

      if (!empty($caption)) {
        \Drupal::logger('media')->notice('AI caption generated successfully: @caption', ['@caption' => $caption]);
        $entity->set('field_ai_caption', $caption);
        $entity->save();
        \Drupal::logger('media')->notice('AI caption saved to media entity.');
      } else {
        \Drupal::logger('media')->warning('No AI caption generated.');
      }
    } else {
      \Drupal::logger('media')->notice('The uploaded media is not an image, skipping AI caption generation.');
    }
  }

  protected function generateCaptionFromAI(Media $media) {
    $file = $media->get('field_media_image')->entity;
    if (!$file) {
      \Drupal::logger('media')->warning('No file found in media entity.');
      return '';
    }

    $imageUrl = file_create_url($file->getFileUri());
    \Drupal::logger('media')->notice('Generated image URL: @url', ['@url' => $imageUrl]);

    try {
      $client = $this->httpClientFactory->fromOptions([
        'headers' => [
          'Authorization' => 'Bearer hf_xx123',
          'Content-Type' => 'application/json',
        ],
      ]);

      $response = $client->post('https://api-inference.huggingface.co/models/nlpconnect/vit-gpt2-image-captioning', [
        'json' => [
          'inputs' => $imageUrl,
          'options' => ['wait_for_model' => true],
        ],
      ]);

      $responseBody = $response->getBody()->getContents();
      \Drupal::logger('media')->notice('API Response body: @response', ['@response' => $responseBody]);

      $data = json_decode($responseBody, TRUE);

      if (isset($data[0]['generated_text'])) {
        \Drupal::logger('media')->notice('AI Caption generated: @caption', ['@caption' => $data[0]['generated_text']]);
        return $data[0]['generated_text'];
      } else {
        \Drupal::logger('media')->warning('No caption received from the AI API.');
      }
    } catch (\Exception $e) {
      \Drupal::logger('media')->error('AI caption generation failed: @message', ['@message' => $e->getMessage()]);
    }

    return '';
  }
}
