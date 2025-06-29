<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // Laravel's HTTP client, powered by Guzzle
use Illuminate\Support\Facades\Log;   // For logging errors

class GoverknowerController extends Controller
{
    /**
     * Handle the /api/ask request.
     * Receives a query, simulates vector DB lookup, builds prompt,
     * calls Gemini API, and returns a natural language response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ask(Request $request)
    {

        if (!$request->has('query')) {
            return response()->json([
                'error' => 'No query provided'
            ], 400);
        }

        $userQuery = $request->input('query');

        // Check for the Gemini API Key
        $geminiApiKey = env('GEMINI_API_KEY');
        if (empty($geminiApiKey)) {
            Log::error('GEMINI_API_KEY is not set in the .env file.');
            return response()->json([
                'error' => 'Server configuration error: Gemini API key not found.'
            ], 500);
        }

        try {
            // --- STEP 1: Simulate Vector DB Query ---
            // In your actual implementation, this is where you would integrate with Pinecone.
            // You would take 'userQuery', generate an embedding, query Pinecone,
            // and get relevant results (e.g., document chunks, factual statements).
            // For this example, we'll use a mock response.

            // Placeholder for Pinecone integration:
            /*
            use Pinecone\PineconeClient; // Assuming you've installed the PHP client
            $pineconeClient = new PineconeClient([
                'apiKey' => env('PINECONE_API_KEY'),
                'environment' => env('PINECONE_ENVIRONMENT')
            ]);
            $index = $pineconeClient->index('your-index-name');

            // You'd generate an embedding for the userQuery here
            // e.g., $userQueryEmbedding = callToEmbeddingService($userQuery);

            $queryResponse = $index->query([
                'vector' => [// $userQueryEmbedding //],
                'topK' => 3, // Get top 3 relevant results
                'includeMetadata' => true,
            ]);

            $vectorDBResults = array_map(function($match) {
                return $match->metadata['text'];
            }, $queryResponse->matches);
            */

            $mockVectorDBResults = [
                "Senator Jane Doe (D-CA) voted YES on the 'Clean Air Act of 2024' (Bill H.R. 1234).",
                "Senator John Smith (R-TX) was ABSENT for the vote on Bill H.R. 1234.",
                "The 'Clean Air Act of 2024' aims to reduce carbon emissions by 20% over 5 years."
            ];
            $vectorDBResponse = implode("\n", $mockVectorDBResults);

            $prompt = "Using the following data, respond to the user's question in a clear and concise manner. If the data does not contain the answer, state that you cannot find the information based on the provided data.\n\n" .
                      "Data:\n{$vectorDBResponse}\n\n" .
                      "User's Question:\n{$userQuery}\n\n" .
                      "Response Format: Provide a direct answer based only on the provided data. Do not preface the answer with 'Here is the answer to your question:'. Do not include any other text in your response, just the result of your research.";

            $geminiEndpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiApiKey}";

            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
            ];

            $response = Http::post($geminiEndpoint, $payload);

            // Check if the API call was successful
            if ($response->failed()) {
                Log::error('Gemini API call failed:', ['status' => $response->status(), 'response' => $response->body()]);
                return response()->json([
                    'error' => 'Failed to get response from Gemini API.',
                    'details' => $response->body()
                ], 500);
            }

            $geminiResult = $response->json();

            Log::info('Gemini API response:', $geminiResult);

            // Extract the text response from Gemini's JSON
            $geminiResponseText = '';
            if (isset($geminiResult['candidates'][0]['content']['parts'][0]['text'])) {
                $geminiResponseText = $geminiResult['candidates'][0]['content']['parts'][0]['text'];
            } else {
                Log::warning('Gemini API response structure unexpected:', ['response' => $geminiResult]);
                return response()->json([
                    'error' => 'Unexpected response from Gemini API.',
                    'details' => $geminiResult
                ], 500);
            }

            // --- STEP 4: Return Natural Language Response to Frontend ---
            return response()->json(['response' => $geminiResponseText]);

        } catch (\Exception $e) {
            Log::error('Error in GoverknowerController:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => 'An internal server error occurred.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
