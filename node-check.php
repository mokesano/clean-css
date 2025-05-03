<?php
$command = "node -v 2>&1"; // Cek versi Node.js
exec($command, $output, $status);

echo "<h3>Hasil Cek Node.js</h3>";
echo "<pre>";
echo "Command: $command\n";
echo "Status: $status\n";
echo "Output:\n" . implode("\n", $output);
echo "</pre>";
?>
