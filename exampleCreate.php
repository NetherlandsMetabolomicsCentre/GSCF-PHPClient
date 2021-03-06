#!/usr/bin/php -q
<?php
/**
 * Example how to interface with the GSCF Api using PHP
 *
 * Api docs     : http://studies.dbnp.org/api
 *
 * Usage        : ./exampleCreate.php
 *                      or
 *                php exampleCreate.php
 *
 * Note         : the user should have the ROLE_CLIENT defined
 *                the api key can be found on the user's profile page
 *
 *
 *
 * Copyright 2012 Jeroen Wesbeek <work@osx.eu>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors',true);

// require GSCF class
require_once("gscf.php");

// instantiate GSCF class and
// set api endpoint and credentials
$gscf = new GSCF();
$gscf->cachePath('/tmp');
$gscf->url('http://studies.mydomain.com');              // set this with the main GSCF URL
$gscf->username('');                                    // set this with the username
$gscf->password('');                                    // set this with the password
$gscf->apiKey('');                                      // set this with the api key for this user

// fetch all readable studies
//$studies = $gscf->getStudies();
//printf("%d studies\n",count($studies));

// get entity types
$entityTypes = $gscf->getEntityTypes();
print_r($entityTypes);

// get modules
$modules = $gscf->getModules();
print_r($modules);

// get all templates for the different entities
foreach ($entityTypes as $entityType) {
    $templates = $gscf->getTemplatesForEntity($entityType);
    print_r($templates);

    // fetch all fields for these templates
    foreach ($templates as $template) {
        // get entity fields for assay
//        $entityFields = $gscf->getFieldsForEntity($entityType, "94c7bd33-b2b7-47fa-9a5f-ff4bb29834c2");
//        print "entity fields for entity 94c7bd33-b2b7-47fa-9a5f-ff4bb29834c2:\n";
//        print_r($entityFields);

        // get entity fields
        $entityFields = $gscf->getFieldsForEntity($entityType);
        print "entity fields:\n";
        print_r($entityFields);

        // get template fields
        $templateFields = $gscf->getFieldsForEntityWithTemplate($entityType, $template.token);
        print "template fields:\n";
        print_r($templateFields);
    }
}

/**
// create a new assay
$fields = array(
    'name'      => 'paperclip',
    'module'    => 'Metabolomics module'
);
$gscf->createEntityWithTemplate(
    'Assay',                                    // entity
    '3d07deb4-9a39-487c-8415-bd37a25a9adb',     // templateToken
    $fields,                                    // (template) fields to set
    array(                                      // relationships
        'studyToken'    => '5660db19-f19a-4c6c-9a51-d35cc7962a2f'
    )
);

// create a new template less assay
$gscf->createEntity(
    'Assay',                                    // entity
    $fields,                                    // fields to set
    array(                                      // relationships
        'studyToken'    => '5660db19-f19a-4c6c-9a51-d35cc7962a2f'
    )
);
**/
?>
