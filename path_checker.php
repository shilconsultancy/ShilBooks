<?php

echo "Please set your BASE_PATH in config.php to the following value (including the slashes):";
echo "<br><br>";
echo "<b>" . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/' . "</b>";