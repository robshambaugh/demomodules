# Acquia DAM

Provides integration between your Drupal site and Acquia DAM.

## Installation

Please refer to the Acquia DAM product documentation for up-to-date installation documentation: https://community.widen.com/collective/s/article/How-do-I-configure-the-Drupal-module?

## Cron

Acquia DAM defines its own Cron job which will update DAM media entities version id value based on the current finalised version set on the API.
On every cron run this custom cron will get the list of assets from the API, which was edited / updated since the last cron run.
Once the items are gathered, those assets of needing an update will be queued for processing.
Following command can be used to process those queue items alongside cron:

```sh
drush queue:run acquia_dam_media_item_update
```
##Adding an Entity revision reference (ERR) widget to include revisioning:
The module provides its own custom widget to deal with revision enabled. In order to do so, the content type should have an ERR field.
Steps to create an ERR field:
- Go to **Structure > Content Type > {Content type name} > Manage field.**
- Click on add field. On the Add a new field dropdown search for the parent option Reference revision and click its child other. Provide the label and click save.
- On the next page under the Type of item to reference select Media. Click save.
- Just like how one deals with Entity Reference configuration, treat the remaining steps as the same.
- Once the field is added go to the form display tab of the Manage content type page. Enable the field and select the widget as Entity Revision Asset Media Library.
- The configuration part is done save it. Now you can use the widget to track asset's revision. 
