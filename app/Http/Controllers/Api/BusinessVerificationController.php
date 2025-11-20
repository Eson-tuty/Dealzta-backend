<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\BusinessVerification;
use App\Models\BusinessVerificationIndustry;
use App\Models\BusinessVerificationDocument;

class BusinessVerificationController extends Controller
{
    private function cacheKey($userId)
    {
        return "business_verification_draft_{$userId}";
    }

    /**
     * Save each page data into Redis
     */
    public function saveStep(Request $request, $step)
    {
        $user = auth()->user();
        $key = $this->cacheKey($user->user_id);

        // Get current cached draft (if any)
        $existing = Cache::get($key, []);

        // Merge new step data
        $existing["step_$step"] = $request->all();

        // Store to redis (valid for 7 days)
        Cache::put($key, $existing, now()->addDays(7));

        return response()->json([
            'message' => "Step $step saved successfully",
            'cache' => $existing
        ]);
    }

    /**
     * Fetch cached form data
     */
    public function getCache()
    {
        $user = auth()->user();
        $key = $this->cacheKey($user->user_id);

        return response()->json([
            'data' => Cache::get($key)
        ]);
    }

    /**
     * Clear draft cache
     */
    public function clearCache()
    {
        $user = auth()->user();
        Cache::forget($this->cacheKey($user->user_id));

        return response()->json(['message' => 'Draft cleared']);
    }

    /**
     * Final submit (merge all steps and save into MySQL)
     */
    public function submit(Request $request)
    {
        $user = auth()->user();
        $key = $this->cacheKey($user->user_id);

        $draft = Cache::get($key);

        if (!$draft) {
            return response()->json(['error' => 'No draft data found'], 400);
        }

        // Merge all step data into one array
        $mergedData = [];
        foreach ($draft as $stepData) {
            $mergedData = array_merge($mergedData, $stepData);
        }

        DB::beginTransaction();

        try {
            // 1. Create business verification entry
            $verification = BusinessVerification::create([
                'user_id' => $user->user_id,
                'business_name' => $mergedData['businessName'] ?? null,
                'business_description' => $mergedData['businessDescription'] ?? null,
                'business_type' => $mergedData['businessType'] ?? null,
                'business_country' => $mergedData['businessCountry'] ?? 'India',

                'registration_number' => $mergedData['registrationNumber'] ?? null,
                'registration_date' => $mergedData['registrationDate'] ?? null,
                'has_registration' => $mergedData['hasRegistration'] ?? 0,
                'gst_verified' => 0,

                'owner_name' => $mergedData['ownerName'] ?? null,
                'owner_email' => $mergedData['ownerEmail'] ?? null,
                'phone_number' => $mergedData['phoneNumber'] ?? null,
                'alternative_phone' => $mergedData['alternativePhone'] ?? null,
                'website' => $mergedData['website'] ?? null,

                'business_address' => $mergedData['businessAddress'] ?? null,
                'location_address_line' => $mergedData['locationAddress'] ?? null,
                'city' => $mergedData['city'] ?? null,
                'state' => $mergedData['state'] ?? null,
                'postal_code' => $mergedData['postalCode'] ?? null,
                'latitude' => $mergedData['latitude'] ?? null,
                'longitude' => $mergedData['longitude'] ?? null,

                'annual_revenue' => $mergedData['annualRevenue'] ?? null,
                'number_of_employees' => $mergedData['numEmployees'] ?? null,
                'years_in_business' => $mergedData['yearsInBusiness'] ?? null,

                'account_holder_name' => $mergedData['accountHolderName'] ?? null,
                'account_number' => $mergedData['accountNumber'] ?? null,
                'ifsc_routing' => $mergedData['ifsc'] ?? null,
                'bank_name' => $mergedData['bankName'] ?? null,
                'branch_name' => $mergedData['branchName'] ?? null,
                'upi_id' => $mergedData['upi'] ?? null,

                'terms_accepted' => $mergedData['termsAccepted'] ?? 0,
                'status' => 'submitted',

                // save full form data
                'meta' => json_encode($mergedData),
            ]);

            /**
             * --------------------------------------------------------------------
             * 2. Save selected industries (industry_category + industry rows)
             * --------------------------------------------------------------------
             */
            if (!empty($mergedData['industries']) && is_array($mergedData['industries'])) {

                // Convert string/number to integer array
                $industryIds = array_map('intval', $mergedData['industries']);

                // save JSON
                $verification->update([
                    'industry_category' => $industryIds
                ]);

                foreach ($industryIds as $i => $industryId) {
                    $category = Category::find($industryId);

                    BusinessVerificationIndustry::create([
                        'verification_id' => $verification->id,
                        'industry_key' => $industryId,
                        'display_label' => $category->name ?? $category->label ?? 'Unknown',
                        'selection_order' => $i + 1,
                    ]);
                }
            }


            /**
             * --------------------------------------------------------------------
             * 3. Save businessLicense document
             * --------------------------------------------------------------------
             */
            BusinessVerificationDocument::create([
                'verification_id' => $verification->id,
                'user_id' => $user->user_id,
                'doc_type' => 'businessLicense',
                'file_path' => $mergedData['businessLicense'] ?? null,
                'file_name' => basename($mergedData['businessLicense'] ?? ''),
            ]);

            /**
             * --------------------------------------------------------------------
             * 4. Save shopImage document
             * --------------------------------------------------------------------
             */
            BusinessVerificationDocument::create([
                'verification_id' => $verification->id,
                'user_id' => $user->user_id,
                'doc_type' => 'shopImage',
                'file_path' => $mergedData['shopImage'] ?? null,
                'file_name' => basename($mergedData['shopImage'] ?? ''),
            ]);

            /**
             * --------------------------------------------------------------------
             * 5. Save additionalCertificate (optional)
             * --------------------------------------------------------------------
             */
            if (!empty($mergedData['additionalCertificate'])) {
                BusinessVerificationDocument::create([
                    'verification_id' => $verification->id,
                    'user_id' => $user->user_id,
                    'doc_type' => 'additionalCertificate',
                    'file_path' => $mergedData['additionalCertificate'],
                    'file_name' => basename($mergedData['additionalCertificate']),
                ]);
            }

            /**
             * --------------------------------------------------------------------
             * 6. Save all dynamic uploaded documents
             * --------------------------------------------------------------------
             */
            if (isset($mergedData['documents']) && is_array($mergedData['documents'])) {
                foreach ($mergedData['documents'] as $doc) {
                    BusinessVerificationDocument::create([
                        'verification_id' => $verification->id,
                        'user_id' => $user->user_id,
                        'doc_type' => $doc['type'],
                        'file_path' => $doc['path'],
                        'file_name' => $doc['name'],
                        'mime_type' => $doc['mime'] ?? null,
                        'size' => $doc['size'] ?? null,
                    ]);
                }
            }

            // Remove cached draft after saving
            Cache::forget($key);

            DB::commit();

            return response()->json([
                'message' => 'Business verification submitted successfully!',
                'verification_id' => $verification->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }


    public function uploadDocument(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:5120', // 5MB
            'type' => 'required|string'
        ]);

        $user = auth()->user();

        $file = $request->file('file');

        $path = $file->store("business_docs/{$user->user_id}", 'public');

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => asset("storage/" . $path)
        ]);
    }

    public function getBusinessProfile()
    {
        $user = auth()->user();

        // Get latest verification entry
        $verification = BusinessVerification::with([
            'industries',
            'documents'
        ])
            ->where('user_id', $user->user_id)
            ->latest()
            ->first();

        if (!$verification) {
            return response()->json([
                'error' => 'Business profile not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'basic' => $verification,
                'industries' => $verification->industries,
                'documents' => $verification->documents,
                'industry_category' => $verification->industry_category // array [3,5,9,1,16]
            ]
        ]);
    }

    public function myBusinesses()
    {
        $user = auth()->user();

        $businesses = BusinessVerification::where('user_id', $user->user_id)
            ->select('id', 'business_name', 'business_type', 'industry_category')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $businesses
        ]);
    }


    public function getProfile($id)
    {
        $business = BusinessVerification::with(['industries', 'documents'])
            ->find($id);

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $business
        ]);
    }

    public function updateDescription(Request $request, $id)
    {
        $request->validate([
            'business_description' => 'required|string'
        ]);

        $business = BusinessVerification::where('id', $id)
            ->where('user_id', auth()->user()->user_id)
            ->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }

        $business->business_description = $request->business_description;
        $business->save();

        return response()->json([
            'success' => true,
            'message' => 'Description updated successfully',
            'data' => $business
        ]);
    }

    public function updateBankDetails(Request $request, $id)
    {
        $request->validate([
            'account_holder_name' => 'required|string',
            'account_number' => 'required|string',
            'ifsc_routing' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'branch_name' => 'nullable|string',
            'upi_id' => 'nullable|string',
            'institution_number' => 'nullable|string'
        ]);

        $business = BusinessVerification::where('id', $id)
            ->where('user_id', auth()->user()->user_id)
            ->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }

        $business->update([
            'account_holder_name' => $request->account_holder_name,
            'account_number' => $request->account_number,
            'ifsc_routing' => $request->ifsc_routing,
            'bank_name' => $request->bank_name,
            'branch_name' => $request->branch_name,
            'upi_id' => $request->upi_id,
            'institution_number' => $request->institution_number,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bank details updated successfully',
            'data' => $business
        ]);
    }





}
