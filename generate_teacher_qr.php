<?php
// Example code para sa Teacher Module sa pag-generate ng QR JSON

$class_id = 101;     // ID ng Subject/Class ni Teacher
$teacher_id = 45;    // ID ni Teacher

// JSON Payload na ipapaloob sa QR Code
$qr_payload = json_encode([
    'class_id'   => $class_id,
    'teacher_id' => $teacher_id,
    'timestamp'  => time()
]);

// Gamit ang anumang JS QR Generator (hal. QRCode.js), i-encode ang variable na `$qr_payload`
?>

<!-- HTML Example sa Teacher Side -->
<div id="qrcode"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    var qrPayload = '<?php echo $qr_payload; ?>';
    new QRCode(document.getElementById("qrcode"), {
        text: qrPayload,
        width: 256,
        height: 256
    });
</script>