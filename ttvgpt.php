<?php
/*
Plugin Name: Tekst TV GPT
Description: Maakt met OpenAI's GPT een samenvatting van een artikel voor op Tekst TV en plaatst dit in het juiste ACF-veld
Version: 0.2
Author: Raymon Mens
*/

require_once(plugin_dir_path(__FILE__) . 'options.php');

class TekstTVGPT {
    private $api_key;
    private $word_limit = 100;

    public function __construct() {
        $this->api_key = get_option('ttvgpt_api_key', '');
        $this->word_limit = get_option('ttvgpt_word_limit', 100);

        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_footer', array($this, 'generate_summary_button'));
        add_action('wp_ajax_generate_summary', array($this, 'generate_summary_ajax'));
    }

    public function enqueue_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        wp_enqueue_script('article-summary-generator', plugin_dir_url(__FILE__) . 'ttvgpt.js', array('jquery'), '1.0', true);
        wp_localize_script('article-summary-generator', 'ttvgpt_ajax_vars', array(
            'nonce' => wp_create_nonce('ttvgpt-ajax-nonce')
        ));
    }

    public function generate_summary_button() {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const textarea = document.querySelector('#acf-field_5f21a06d22c58');
                if (textarea) {
                    const button = document.createElement('button');
                    button.textContent = 'Genereer';
                    button.className = 'generate-summary-button button button-secondary';
                    button.style.marginTop = '1em';
                    button.onclick = function(e) {
                        e.preventDefault();
                        if (!button.classList.contains('disabled')) {
                            generateSummary();
                        } else {
                            e.preventDefault();
                        }
                    };

                    textarea.parentElement.appendChild(button);
                }
            });
        </script>
        <?php
    }

    public function generate_summary_ajax() {
        check_ajax_referer('ttvgpt-ajax-nonce', '_ajax_nonce');

        if (isset($_POST['content'])) {
            $content = sanitize_text_field($_POST['content']);
            $summary = $this->generate_gpt_summary($content);
            echo esc_html($summary);
        }

        wp_die();
    }

    private function generate_gpt_summary($content) {
        if (str_word_count($content) < 30) {
            return "Te weinig woorden om een bericht te maken. Ik heb er minimaal 30 nodig...";
        }

        if (empty($this->api_key)) {
            return 'API Key niet ingevuld. Kan geen bericht genereren.';
        }

        $endpoint_url = 'https://api.openai.com/v1/chat/completions';

        $data = [
            'max_tokens' => 256,
            'model' => 'ft:gpt-3.5-turbo-0613:personal::871wJ7cX',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Please summarize the following news article in a clear and concise manner that is easy to understand for a general audience. Use short sentences. Do it in Dutch. Ignore everything in the article that's not a Dutch word. Parse HTML. Never output English words. Use maximal " . $this->word_limit . " words."
                ],
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ]
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ];

        $ch = curl_init($endpoint_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        $summary = $result['choices'][0]['message']['content'];

        return trim($summary);
    }
}

new TekstTVGPT();
