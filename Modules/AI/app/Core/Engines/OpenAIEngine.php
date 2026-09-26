<?php

namespace Modules\AI\app\Core\Engines;

use Modules\AI\app\Core\Contracts\AIEngineInterface;
use Modules\AI\app\Exceptions\AIServiceException;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\TransporterException;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;


class OpenAIEngine implements AIEngineInterface
{
    public function boot(): void
    {
        // TODO: Implement boot() method.
    }

    public function core($prompt, $imageUrl = null): string
    {
        if (empty(config('openai.api_key'))) {
            throw new AIServiceException(translate('The AI service is not configured. Please add a valid OpenAI API key.'));
        }

        $content = [['type' => 'text', 'text' => $prompt]];

        if (!empty($imageUrl)) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $imageUrl],
            ];
        }

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
                'temperature' => 0.3,
            ]);
        } catch (ErrorException $e) {
            throw new AIServiceException(translate('The AI service could not process your request: ') . $e->getMessage());
        } catch (TransporterException $e) {
            throw new AIServiceException(translate('Could not reach the AI service. Please check your connection and try again.'));
        } catch (Throwable $e) {
            throw new AIServiceException(translate('The AI service returned an unexpected response. Please try again later.'));
        }

        if (empty($response->choices) || !isset($response->choices[0]->message->content)) {
            throw new AIServiceException(translate('The AI service returned an empty response. Please try again later.'));
        }

        return $response->choices[0]->message->content;
    }


}


