<?php
/**
 * Test suite for IMG (image resizing) and OBF (encode/decode) classes.
 *
 * Usage:  php tests/test_img_obf.php
 * Exit 0 on success, non-zero on failure.
 */

include(__DIR__ . '/../src/utils.inc.php');


/* ------------------------------------------------------------------ */
/*  Helpers                                                           */
/* ------------------------------------------------------------------ */

$passed  = 0;
$failed  = 0;

function assert_true(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  PASS  {$label}\n";
    } else {
        $failed++;
        echo "  FAIL  {$label}\n";
    }
}

function assert_eq($actual, $expected, string $label): void
{
    assert_true($actual === $expected, "{$label} (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")");
}


/* ------------------------------------------------------------------ */
/*  Create a temporary test image (PNG, 200x100)                      */
/* ------------------------------------------------------------------ */

$tmpDir   = sys_get_temp_dir() . '/pxpros_tests_' . getmypid();
@mkdir($tmpDir, 0777, true);

$srcFile  = $tmpDir . '/source.png';
$srcW     = 200;
$srcH     = 100;
$im       = imagecreatetruecolor($srcW, $srcH);
$red      = imagecolorallocate($im, 255, 0, 0);
imagefilledrectangle($im, 0, 0, $srcW - 1, $srcH - 1, $red);
imagepng($im, $srcFile);
imagedestroy($im);


/* ================================================================== */
/*  IMG Tests                                                         */
/* ================================================================== */

echo "\n=== IMG Tests ===\n";

// 1. Constructor & property accessors
$img = new IMG($srcFile);
assert_eq($img->width, $srcW, 'IMG source width');
assert_eq($img->height, $srcH, 'IMG source height');
assert_eq($img->w, $srcW, 'IMG source w shorthand');
assert_eq($img->h, $srcH, 'IMG source h shorthand');

// 2. Resize – width only (proportional)
$img2 = (new IMG($srcFile))->resize(100);
assert_eq($img2->width, 100, 'Resize width-only: width');
assert_eq($img2->height, 50, 'Resize width-only: height (proportional)');

// 3. Resize – contain (fit inside bounding box)
$img3 = (new IMG($srcFile))->resize(80, 80);
assert_eq($img3->width, 80, 'Resize contain: width');
assert_eq($img3->height, 40, 'Resize contain: height');

// 4. Resize – cover (fill bounding box, crop excess)
$img4 = (new IMG($srcFile))->resize(80, 80, true);
assert_eq($img4->width, 80, 'Resize cover: width');
assert_eq($img4->height, 80, 'Resize cover: height');

// 5. Save to JPEG and verify the file exists and is valid
$jpgFile = $tmpDir . '/output.jpg';
(new IMG($srcFile))->resize(50)->save($jpgFile);
assert_true(is_file($jpgFile), 'Save JPEG: file exists');
$info = getimagesize($jpgFile);
assert_eq($info[0], 50, 'Save JPEG: width');
assert_eq($info[1], 25, 'Save JPEG: height');
assert_eq($info[2], IMAGETYPE_JPEG, 'Save JPEG: format is JPEG');

// 6. Save to PNG and verify
$pngFile = $tmpDir . '/output.png';
(new IMG($srcFile))->resize(60, 40)->save($pngFile);
assert_true(is_file($pngFile), 'Save PNG: file exists');
$info2 = getimagesize($pngFile);
assert_eq($info2[0], 60, 'Save PNG: width');
assert_eq($info2[1], 30, 'Save PNG: height (contain)');
assert_eq($info2[2], IMAGETYPE_PNG, 'Save PNG: format is PNG');

// 7. Invalid source file throws exception
$threw = false;
try { new IMG($tmpDir . '/nonexistent.png'); } catch (Exception $e) { $threw = true; }
assert_true($threw, 'IMG constructor: throws on missing file');


/* ================================================================== */
/*  OBF Tests                                                         */
/* ================================================================== */

echo "\n=== OBF Tests ===\n";

// 1. Encode/decode round-trip with a string
$original = "Hello, pxpros!";
$encoded  = OBF::encode($original);
assert_true(is_string($encoded), 'OBF encode returns string');
assert_true($encoded !== $original, 'OBF encoded differs from original');

$decoded = OBF::decode($encoded);
assert_eq($decoded, $original, 'OBF decode round-trip (string)');

// 2. Encode/decode round-trip with an associative array / object
$data = ['name' => 'test', 'value' => 42, 'nested' => ['a' => true]];
$encoded2 = OBF::encode($data);
$decoded2 = OBF::decode($encoded2);
assert_eq($decoded2->name, 'test', 'OBF round-trip object: name');
assert_eq($decoded2->value, 42, 'OBF round-trip object: value');
assert_eq($decoded2->nested->a, true, 'OBF round-trip object: nested');

// 3. Encode/decode with empty data
$encoded3 = OBF::encode("");
$decoded3 = OBF::decode($encoded3);
assert_eq($decoded3, "", 'OBF round-trip: empty string');

// 4. Encode/decode with special characters
$special  = "Héllo <world> & \"quotes\" à la française!";
$encoded4 = OBF::encode($special);
$decoded4 = OBF::decode($encoded4);
assert_eq($decoded4, $special, 'OBF round-trip: special characters');

// 5. Encode/decode with numeric array
$arr = [1, 2, 3, 4, 5];
$encoded5 = OBF::encode($arr);
$decoded5 = OBF::decode($encoded5);
assert_eq(count($decoded5), 5, 'OBF round-trip array: count');
assert_eq($decoded5[0], 1, 'OBF round-trip array: first element');
assert_eq($decoded5[4], 5, 'OBF round-trip array: last element');

// 6. Encoded output is not trivially readable (not plain JSON or base64)
$enc = OBF::encode("secret data");
$jsonAttempt = @json_decode($enc);
assert_true($jsonAttempt === null, 'OBF encoded is not plain JSON');
$b64Attempt  = @base64_decode($enc, true);
$isPlainB64  = ($b64Attempt !== false && @json_decode($b64Attempt) !== null);
assert_true(!$isPlainB64, 'OBF encoded is not plain base64 JSON');


/* ================================================================== */
/*  Cleanup                                                           */
/* ================================================================== */

FS::rmdir($tmpDir);


/* ================================================================== */
/*  Summary                                                           */
/* ================================================================== */

echo "\n--- Results: {$passed} passed, {$failed} failed ---\n";
exit($failed > 0 ? 1 : 0);
