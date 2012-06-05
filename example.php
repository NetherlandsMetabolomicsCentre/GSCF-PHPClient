#!/usr/bin/php -q
<?php
/**
 * Example script which pulls (readable) studies and assays
 * from a GSCF instance
 *
 * Usage:
 *  ./example.php
 *      or
 *  php example.php
 *
 * Note: - the user should have the ROLE_CLIENT defined
 *       - the api key can be found on the user's profile page
 *
 * @author Jeroen Wesbeek <work@osx.eu>
 * @since  20120410
 *
 * $Rev$
 * $Id$
 */

// enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors',true);

// require GSCF class
require_once("gscf.php");

// instantiate GSCF class and
// set api endpoint and credentials
$gscf = new GSCF();
$gscf->url('http://studies.mydomain.com');	// set this with the main GSCF URL
$gscf->username('');				        // set this with the username
$gscf->password('');				        // set this with the password
$gscf->apiKey('');				            // set this with the api key for this user

// fetch all readable studies
$studies = $gscf->getStudies();

printf("%d studies\n",count($studies));

// iterate through studies
foreach ($studies as $study) {
	printf("Study	: %s\n",$study->title);
	
	// fetch subjects for this study
	$subjects = $study->getSubjects();

	// list the subjects
	foreach ($subjects as $subject) {
		printf("\t\tsubject: %s\n",$subject->name);
	}

	// fetch the assays for this study
	$assays = $study->getAssays();

	// list the assays
	foreach ($assays as $assay) {
		printf("\t\tassay: %s\n",$assay->name);

		// list the samples for this assay
		$samples = $assay->getSamples();
		foreach ($samples as $sample) {
			printf("\t\t\tsample: %s\n",$sample->name);
		}

		// list the measurement data for this assay
		$measurementData = $assay->getMeasurementData();
		foreach ($measurementData as $data) {
			printf("\t\t\tmeasurement data: %s\n",serialize($data));
		}		
	}
}
?>
