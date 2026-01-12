<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Get public settings (for frontend footer, contact pages, etc.)
     * No authentication required
     */
    public function publicSettings()
    {
        return response()->json([
            'site_name' => AdminSetting::get('site_name', 'Hajz'),
            'support_email' => AdminSetting::get('support_email', 'support@hajz.dz'),
            'support_phone' => AdminSetting::get('support_phone', '+213 123 456 789'),
            'address' => AdminSetting::get('address', 'Algiers, Algeria'),
            'facebook_url' => AdminSetting::get('facebook_url', ''),
            'instagram_url' => AdminSetting::get('instagram_url', ''),
            'twitter_url' => AdminSetting::get('twitter_url', ''),
            'linkedin_url' => AdminSetting::get('linkedin_url', ''),
        ]);
    }

    public function index()
    {
        // Return settings in the format expected by frontend
        return response()->json([
            'commission_rate' => (float) AdminSetting::get('commission_rate', 10),
            'min_withdrawal_amount' => (int) AdminSetting::get('min_withdrawal_amount', 1000),
            'site_name' => AdminSetting::get('site_name', 'Hajz'),
            'support_email' => AdminSetting::get('support_email', 'support@hajz.dz'),
            'support_phone' => AdminSetting::get('support_phone', '+213 123 456 789'),
            'address' => AdminSetting::get('address', 'Algiers, Algeria'),
            'facebook_url' => AdminSetting::get('facebook_url', ''),
            'instagram_url' => AdminSetting::get('instagram_url', ''),
            'twitter_url' => AdminSetting::get('twitter_url', ''),
            'linkedin_url' => AdminSetting::get('linkedin_url', ''),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'commission_rate' => 'sometimes|numeric|min:0|max:100',
            'min_withdrawal_amount' => 'sometimes|numeric|min:0',
            'site_name' => 'sometimes|string|max:255',
            'support_email' => 'sometimes|email|max:255',
            'support_phone' => 'sometimes|string|max:50',
            'address' => 'sometimes|string|max:500',
            'facebook_url' => 'sometimes|nullable|string|max:255',
            'instagram_url' => 'sometimes|nullable|string|max:255',
            'twitter_url' => 'sometimes|nullable|string|max:255',
            'linkedin_url' => 'sometimes|nullable|string|max:255',
        ]);

        $updated = [];

        // Update commission rate
        if (isset($validated['commission_rate'])) {
            AdminSetting::set('commission_rate', $validated['commission_rate'], 'Commission percentage taken from each booking');
            $updated['commission_rate'] = $validated['commission_rate'];
        }

        // Update minimum withdrawal amount
        if (isset($validated['min_withdrawal_amount'])) {
            AdminSetting::set('min_withdrawal_amount', $validated['min_withdrawal_amount'], 'Minimum amount professionals can withdraw');
            $updated['min_withdrawal_amount'] = $validated['min_withdrawal_amount'];
        }

        // Update site name
        if (isset($validated['site_name'])) {
            AdminSetting::set('site_name', $validated['site_name'], 'Platform site name');
            $updated['site_name'] = $validated['site_name'];
        }

        // Update support email
        if (isset($validated['support_email'])) {
            AdminSetting::set('support_email', $validated['support_email'], 'Support contact email');
            $updated['support_email'] = $validated['support_email'];
        }

        // Update support phone
        if (isset($validated['support_phone'])) {
            AdminSetting::set('support_phone', $validated['support_phone'], 'Support contact phone');
            $updated['support_phone'] = $validated['support_phone'];
        }

        // Update address
        if (isset($validated['address'])) {
            AdminSetting::set('address', $validated['address'], 'Business address');
            $updated['address'] = $validated['address'];
        }

        // Update social media links
        if (array_key_exists('facebook_url', $validated)) {
            AdminSetting::set('facebook_url', $validated['facebook_url'] ?? '', 'Facebook page URL');
            $updated['facebook_url'] = $validated['facebook_url'];
        }

        if (array_key_exists('instagram_url', $validated)) {
            AdminSetting::set('instagram_url', $validated['instagram_url'] ?? '', 'Instagram page URL');
            $updated['instagram_url'] = $validated['instagram_url'];
        }

        if (array_key_exists('twitter_url', $validated)) {
            AdminSetting::set('twitter_url', $validated['twitter_url'] ?? '', 'Twitter/X page URL');
            $updated['twitter_url'] = $validated['twitter_url'];
        }

        if (array_key_exists('linkedin_url', $validated)) {
            AdminSetting::set('linkedin_url', $validated['linkedin_url'] ?? '', 'LinkedIn page URL');
            $updated['linkedin_url'] = $validated['linkedin_url'];
        }

        return response()->json([
            'message' => 'Settings updated successfully',
            'updated' => $updated,
        ]);
    }

    /**
     * Get legal content for admin panel
     */
    public function getLegalContent()
    {
        return response()->json([
            'privacy_policy_en' => AdminSetting::get('privacy_policy_en', ''),
            'privacy_policy_ar' => AdminSetting::get('privacy_policy_ar', ''),
            'privacy_policy_fr' => AdminSetting::get('privacy_policy_fr', ''),
            'terms_of_use_en' => AdminSetting::get('terms_of_use_en', ''),
            'terms_of_use_ar' => AdminSetting::get('terms_of_use_ar', ''),
            'terms_of_use_fr' => AdminSetting::get('terms_of_use_fr', ''),
        ]);
    }

    /**
     * Update legal content from admin panel
     */
    public function updateLegalContent(Request $request)
    {
        $validated = $request->validate([
            'privacy_policy_en' => 'sometimes|nullable|string',
            'privacy_policy_ar' => 'sometimes|nullable|string',
            'privacy_policy_fr' => 'sometimes|nullable|string',
            'terms_of_use_en' => 'sometimes|nullable|string',
            'terms_of_use_ar' => 'sometimes|nullable|string',
            'terms_of_use_fr' => 'sometimes|nullable|string',
        ]);

        $fields = [
            'privacy_policy_en' => 'Privacy Policy (English)',
            'privacy_policy_ar' => 'Privacy Policy (Arabic)',
            'privacy_policy_fr' => 'Privacy Policy (French)',
            'terms_of_use_en' => 'Terms of Use (English)',
            'terms_of_use_ar' => 'Terms of Use (Arabic)',
            'terms_of_use_fr' => 'Terms of Use (French)',
        ];

        foreach ($fields as $key => $description) {
            if (array_key_exists($key, $validated)) {
                AdminSetting::set($key, $validated[$key] ?? '', $description);
            }
        }

        return response()->json([
            'message' => 'Legal content updated successfully',
        ]);
    }

    /**
     * Get public legal content (for frontend pages)
     * No authentication required
     */
    public function getPublicLegalContent(Request $request)
    {
        $type = $request->query('type', 'privacy'); // privacy or terms
        $lang = $request->query('lang', 'en'); // en, ar, fr

        // Validate inputs
        if (!in_array($type, ['privacy', 'terms'])) {
            $type = 'privacy';
        }
        if (!in_array($lang, ['en', 'ar', 'fr'])) {
            $lang = 'en';
        }

        $key = $type === 'privacy' ? "privacy_policy_{$lang}" : "terms_of_use_{$lang}";
        $content = AdminSetting::get($key, '');

        return response()->json([
            'type' => $type,
            'lang' => $lang,
            'content' => $content,
        ]);
    }
}
