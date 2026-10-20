<?php
declare(strict_types=1);

const FOOD_CATEGORIES = ['cooked_meals'=>'Cooked meals','bakery'=>'Bakery','produce'=>'Fruit & vegetables',
    'dry_goods'=>'Dry goods','dairy'=>'Dairy','other'=>'Other food'];
const FOOD_UNITS = ['portions'=>'Portions','kg'=>'Kilograms','litres'=>'Litres','items'=>'Items','packs'=>'Packs'];

function donation_datetime(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    return $date !== false && $date->format('Y-m-d\TH:i') === $value ? $date : null;
}

function validate_donation(array $values): array
{
    $errors = [];
    foreach ($values as $value) {
        if (!mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            return ['Use valid text without null characters.'];
        }
    }
    foreach (['food_name'=>[2,150,'Food name'],'description'=>[10,3000,'Description'],
        'pickup_location'=>[5,255,'Pickup location']] as $field=>$rule) {
        if (mb_strlen($values[$field]) < $rule[0] || mb_strlen($values[$field]) > $rule[1]) {
            $errors[] = $rule[2] . ' must contain ' . $rule[0] . ' to ' . $rule[1] . ' characters.';
        }
    }
    if (!isset(FOOD_CATEGORIES[$values['category']])) { $errors[] = 'Choose a valid food category.'; }
    if (!isset(FOOD_UNITS[$values['quantity_unit']])) { $errors[] = 'Choose a valid quantity unit.'; }
    if (!preg_match('/\A(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?\z/', $values['quantity'])
        || (float) $values['quantity'] <= 0) {
        $errors[] = 'Enter a quantity above zero, up to 99,999,999.99, with at most two decimal places.';
    }
    $available = donation_datetime($values['available_at']);
    $deadline = donation_datetime($values['deadline_at']);
    if ($available === null || $deadline === null) {
        $errors[] = 'Enter valid available and deadline dates and times.';
    } elseif ($deadline <= $available || $deadline <= new DateTimeImmutable()) {
        $errors[] = 'The deadline must be in the future and after the available time.';
    }
    return $errors;
}

function store_food_image(?array $file): ?string
{
    if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) { return null; }
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('The image could not be uploaded. Choose an image up to 2 MB.');
    }
    if (!is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])
        || !is_int($file['size'] ?? null) || $file['size'] < 1 || $file['size'] > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Choose a valid image file up to 2 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extension = is_string($file['name'] ?? null) ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';
    $extensions = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
    $dimensions = @getimagesize($file['tmp_name']);
    if (!isset($extensions[$extension]) || $extensions[$extension] !== $mime || !$dimensions
        || $dimensions['mime'] !== $mime || $dimensions[0] > 4096 || $dimensions[1] > 4096
        || $dimensions[0] * $dimensions[1] > 8000000) {
        throw new InvalidArgumentException('Use a JPEG, PNG, or WebP image up to 4096 pixels per side and 8 megapixels.');
    }
    // Decode and re-encode: never store the submitted file or its original name.
    $source = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    if ($source === false) { throw new InvalidArgumentException('This image cannot be read. Choose another image.'); }
    $scale = min(1, 1600 / max($dimensions[0], $dimensions[1]));
    $width = max(1, (int) round($dimensions[0] * $scale));
    $height = max(1, (int) round($dimensions[1] * $scale));
    $image = imagecreatetruecolor($width, $height);
    $path = 'uploads/donations/' . bin2hex(random_bytes(16)) . '.jpg';
    $absolute = __DIR__ . '/../' . $path;
    try {
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $dimensions[0], $dimensions[1]);
        if (!is_dir(dirname($absolute)) || !is_writable(dirname($absolute)) || !@imagejpeg($image, $absolute, 85)) {
            if (is_file($absolute)) { unlink($absolute); }
            throw new RuntimeException('Image storage is temporarily unavailable. Please try again later.');
        }
    } finally {
        imagedestroy($image);
        imagedestroy($source);
    }
    return $path;
}

function remove_food_image(?string $path): void
{
    if ($path !== null && preg_match('/\Auploads\/donations\/[a-f0-9]{32}\.jpg\z/', $path)) {
        $absolute = __DIR__ . '/../' . $path;
        if (is_file($absolute)) { unlink($absolute); }
    }
}
