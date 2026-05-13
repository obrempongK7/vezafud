<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'post_type'   => 'required|in:photo,video,reel,recipe_share,review,check_in,promotion,event',
            'caption'     => 'nullable|string|max:2200',
            'visibility'  => 'nullable|in:public,followers,private',
            'media'       => 'required|array|min:1',
            'media.*'     => 'file|mimes:jpg,jpeg,png,gif,mp4,mov|max:102400',
            'business_id' => 'nullable|exists:businesses,id',
            'location_name'=>'nullable|string',
            'location_lat'=> 'nullable|numeric',
            'location_lng'=> 'nullable|numeric',
            'hashtags'    => 'nullable|array',
        ];
    }
}
