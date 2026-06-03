<?php

namespace App\Validators;

class HarvestValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['land_id'])) {
            $errors['land_id'] = 'land_id is required.';
        } elseif (!is_numeric($data['land_id']) || (int)$data['land_id'] <= 0) {
            $errors['land_id'] = 'land_id must be a positive integer.';
        }

        if (empty($data['crop_type'])) {
            $errors['crop_type'] = 'crop_type is required.';
        }

        if (!isset($data['yield_ton']) || $data['yield_ton'] === '') {
            $errors['yield_ton'] = 'yield_ton is required.';
        } elseif (!is_numeric($data['yield_ton']) || (float)$data['yield_ton'] <= 0) {
            $errors['yield_ton'] = 'yield_ton must be greater than 0.';
        }

        if (empty($data['harvest_date'])) {
            $errors['harvest_date'] = 'harvest_date is required.';
        } elseif (!\DateTime::createFromFormat('Y-m-d', $data['harvest_date'])) {
            $errors['harvest_date'] = 'harvest_date must be in YYYY-MM-DD format.';
        }

        return $errors;
    }
}
