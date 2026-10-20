<?php
declare(strict_types=1);

function profile_version(array $user): string
{
    return hash('sha256', json_encode([$user['full_name'],$user['email'],$user['phone'],$user['address']],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function editable_profile_version(array $user,?array $organization=null): string
{
    if ($organization===null) {return profile_version($user);}
    return hash('sha256',json_encode([profile_version($user),$organization['organization_name'],
        $organization['description']??''],JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function validate_profile(array $values): array
{
    $errors=[];
    foreach ($values as $value) {
        if (!mb_check_encoding($value,'UTF-8') || str_contains($value,"\0")) {
            return ['Use valid text without null characters.'];
        }
    }
    if (mb_strlen($values['full_name'])<2 || mb_strlen($values['full_name'])>100) {
        $errors[]='Enter a full name between 2 and 100 characters.';
    }
    if (strlen($values['email'])>191 || !filter_var($values['email'],FILTER_VALIDATE_EMAIL)) {
        $errors[]='Enter a valid email address of up to 191 characters.';
    }
    if (!preg_match('/\A[0-9+().\s-]{7,25}\z/',$values['phone'])
        || !preg_match('/\A[0-9]{7,15}\z/',preg_replace('/\D/','',$values['phone']))) {
        $errors[]='Enter a phone number with 7 to 15 digits.';
    }
    if (mb_strlen($values['address'])<5 || mb_strlen($values['address'])>255) {
        $errors[]='Enter an address between 5 and 255 characters.';
    }
    return $errors;
}
