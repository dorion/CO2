<?php
/**
 * Set the module's constant
 *
 */

//CO2 emission constants
define('CO2_EMISSION_CAR', 176); // g/km
define('CO2_EMISSION_TRAIN', 60); // g/km
define('CO2_EMISSION_AEROPLANE_800', 160); // g/km
define('CO2_EMISSION_AEROPLANE_800_PLUS', 100); // g/km
define('CO2_EMISSION_MCU', 0.0856); // g/s
define('CO2_EMISSION_GATEKEEPER', 0.0176); // g/s
define('CO2_EMISSION_VIDCONF_ENDPOINT', 0.0073); // g/s
define('CO2_EMISSION_VIDCONF_ENDPOINT_DISPLAY', 0.0117); // g/s

//Vehicel distance limit in km
define('CO2_DISTANCE_CAR', 300);
define('CO2_DISTANCE_TRAIN', 500);
define('CO2_DISTANCE_AEROPLANE', 800);

//Average speed for the time etimate
define('CO2_AVERAGE_SPEED_AEROPLANE', 236); //m/s
