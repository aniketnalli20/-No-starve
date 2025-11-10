<?php
// Geolocation provider configuration
// Default provider: LocationIQ (free tier). Create a free key at https://locationiq.com/
// Alternatively, set GEO_PROVIDER to 'nominatim' to use OpenStreetMap without a key.

$GEO_PROVIDER = getenv('GEO_PROVIDER') ?: 'locationiq';
$GEO_API_KEY = getenv('GEO_API_KEY') ?: '';

// You can hardcode the key here if environment variables are not set:
// $GEO_API_KEY = 'YOUR_LOCATIONIQ_KEY';