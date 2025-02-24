# drupal_gsoc : AI-Powered Media Caption Generator
This repo contains the (proof of work of Drupal GSOC'25 issue : AI-Powered Media Caption Generator


Currently working on the above said issue. This repo contains the two methods as my proof of work.
The "hook event dispatcher module" method is in a working condition, as required, the user uploads the media and gets the AI-Generated Caption. Note that the feature "Allow users to choose and configure AI models based on their needs" is yet to be worked upon. 
When I am building with respect to "EventSubscriber" Drupal's core method I am facing certain issues, it's API and Endpoints are working, it has some connectivity issue only that I am yet to figure out. 

ABOUT - Hook Event Dispatcher
This is a contributed module that traps classic Drupal "hooks" (such as hook_entity_insert, hook_entity_update, hook_entity_view, etc.) and fires them as Symfony events (entity.insert, entity.update, etc.).
Without this contributed module, events with names such as "entity.insert" or "entity.update" do not exist in a vanilla Drupal installation.
Drupal Core's EntityEvents

