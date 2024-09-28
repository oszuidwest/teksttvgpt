<?php
// Load WordPress
require_once('../wp-load.php');

// Sanitize and get the year and month from the query string
$year  = isset($_GET['year']) ? absint($_GET['year']) : null;
$month = isset($_GET['month']) ? absint($_GET['month']) : null;

// Redirect to the most recent month with posts if year or month is not specified
if (!$year || !$month) {
    $most_recent_month = get_most_recent_month_with_posts();
    if ($most_recent_month) {
        wp_redirect(add_query_arg([
            'year'  => $most_recent_month['year'],
            'month' => $most_recent_month['month']
        ]));
        exit;
    } else {
        echo "<p>No posts found.</p>";
        exit;
    }
}

// Fetch posts for the selected month
$posts = get_posts_for_month($year, $month);

// Initialize counters for each category
$counts = [
    'fully_human_written'   => 0,
    'ai_written_not_edited' => 0,
    'ai_written_edited'     => 0
];

// Process posts to count each category
foreach ($posts as $post) {
    categorize_post($post, $counts);
}

// Display the dashboard
display_dashboard($posts, $counts, $year, $month);

/**
 * Fetches the most recent month with relevant posts.
 *
 * @return array|null Returns an associative array with 'year' and 'month' or null if no posts found.
 */
function get_most_recent_month_with_posts() {
    global $wpdb;

    $result = $wpdb->get_row("
        SELECT DISTINCT YEAR(p.post_date) AS year, MONTH(p.post_date) AS month
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
        INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
        WHERE pm1.meta_key = 'post_in_kabelkrant'
          AND pm1.meta_value = '1'
          AND pm2.meta_key = 'post_kabelkrant_content_gpt'
          AND pm2.meta_value != ''
          AND p.post_status = 'publish'
        ORDER BY p.post_date DESC
        LIMIT 1
    ");

    return $result ? ['year' => (int) $result->year, 'month' => (int) $result->month] : null;
}

/**
 * Fetches posts for a specific month and year.
 *
 * @param int $year
 * @param int $month
 * @return array
 */
function get_posts_for_month($year, $month) {
    $meta_query = [
        'relation' => 'AND',
        [
            'key'     => 'post_in_kabelkrant',
            'value'   => '1',
            'compare' => '='
        ],
        [
            'key'     => 'post_kabelkrant_content_gpt',
            'compare' => 'EXISTS'
        ],
        [
            'key'     => 'post_kabelkrant_content',
            'compare' => 'EXISTS'
        ]
    ];

    $args = [
        'date_query'     => [
            [
                'year'  => $year,
                'month' => $month
            ]
        ],
        'meta_query'     => $meta_query,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'post_date',
        'order'          => 'DESC'
    ];

    $query = new WP_Query($args);
    return $query->posts;
}

/**
 * Categorizes a post and updates the counts.
 *
 * @param WP_Post $post
 * @param array   &$counts
 */
function categorize_post($post, &$counts) {
    $ai_content    = strip_before_dash(trim(get_post_meta($post->ID, 'post_kabelkrant_content_gpt', true)));
    $human_content = strip_before_dash(trim(get_post_meta($post->ID, 'post_kabelkrant_content', true)));

    if (empty($ai_content)) {
        $counts['fully_human_written']++;
    } elseif ($ai_content === $human_content) {
        $counts['ai_written_not_edited']++;
    } else {
        $counts['ai_written_edited']++;
    }
}

/**
 * Strips content before and including ' - '.
 *
 * @param string $content
 * @return string
 */
function strip_before_dash($content) {
    if (($pos = strpos($content, ' - ')) !== false) {
        return trim(substr($content, $pos + 3));
    }
    return $content;
}

/**
 * Generates a word-by-word diff between two strings.
 *
 * @param string $old
 * @param string $new
 * @return array
 */
function generate_word_diff($old, $new) {
    $old_words = preg_split('/\s+/', trim($old));
    $new_words = preg_split('/\s+/', trim($new));

    // Build the table of longest common subsequence lengths
    $lcs_table = [];
    $old_count = count($old_words);
    $new_count = count($new_words);

    for ($i = 0; $i <= $old_count; $i++) {
        $lcs_table[$i] = array_fill(0, $new_count + 1, 0);
    }

    for ($i = $old_count - 1; $i >= 0; $i--) {
        for ($j = $new_count - 1; $j >= 0; $j--) {
            if ($old_words[$i] === $new_words[$j]) {
                $lcs_table[$i][$j] = $lcs_table[$i + 1][$j + 1] + 1;
            } else {
                $lcs_table[$i][$j] = max($lcs_table[$i + 1][$j], $lcs_table[$i][$j + 1]);
            }
        }
    }

    // Recover the LCS
    $i = $j = 0;
    $lcs = [];
    while ($i < $old_count && $j < $new_count) {
        if ($old_words[$i] === $new_words[$j]) {
            $lcs[] = $old_words[$i];
            $i++;
            $j++;
        } elseif ($lcs_table[$i + 1][$j] >= $lcs_table[$i][$j + 1]) {
            $i++;
        } else {
            $j++;
        }
    }

    // Generate diff
    $diff_before = '';
    $diff_after  = '';
    $i_old = $i_new = $i_lcs = 0;

    while ($i_old < $old_count || $i_new < $new_count) {
        if ($i_lcs < count($lcs) && $i_old < $old_count && $i_new < $new_count && $old_words[$i_old] === $lcs[$i_lcs] && $new_words[$i_new] === $lcs[$i_lcs]) {
            // Words are the same
            $word = esc_html($old_words[$i_old]);
            $diff_before .= "{$word} ";
            $diff_after  .= "{$word} ";
            $i_old++;
            $i_new++;
            $i_lcs++;
        } else {
            // Words are different
            if ($i_old < $old_count && ($i_lcs >= count($lcs) || $old_words[$i_old] !== $lcs[$i_lcs])) {
                $word = esc_html($old_words[$i_old]);
                $diff_before .= "<del class='text-red-500 line-through'>{$word}</del> ";
                $i_old++;
            }
            if ($i_new < $new_count && ($i_lcs >= count($lcs) || $new_words[$i_new] !== $lcs[$i_lcs])) {
                $word = esc_html($new_words[$i_new]);
                $diff_after .= "<ins class='text-green-600 bg-green-100'>{$word}</ins> ";
                $i_new++;
            }
        }
    }

    return [
        'before' => trim($diff_before),
        'after'  => trim($diff_after)
    ];
}

/**
 * Displays the dashboard.
 *
 * @param array $posts
 * @param array $counts
 * @param int   $year
 * @param int   $month
 */
function display_dashboard($posts, $counts, $year, $month) {
    // Start of HTML output
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Tekst TV GPT Dashboard</title>
        <meta name="robots" content="noindex">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- Tailwind CSS CDN -->
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 font-sans">
    <!-- Pagination Top Bar -->
    <?php display_pagination($year, $month); ?>
    <div class="max-w-5xl mx-auto p-8">
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <?php foreach ($counts as $key => $count) :
                $label = ucwords(str_replace('_', ' ', $key)); ?>
                <div class="bg-white p-6 rounded-lg shadow">
                    <h2 class="text-4xl font-bold text-center"><?php echo esc_html($count); ?></h2>
                    <p class="text-center text-gray-600 mt-2"><?php echo esc_html($label); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Post List -->
        <div class="grid grid-cols-1 gap-6">
            <?php
            global $post;
            foreach ($posts as $post) :
                setup_postdata($post);
                $post_id          = $post->ID;
                $post_title       = get_the_title($post_id);
                $post_date        = get_the_date('Y-m-d', $post_id);
                $author_name      = get_the_author_meta('display_name', $post->post_author);
                $last_editor      = get_userdata(get_post_meta($post_id, '_edit_last', true));
                $last_editor_name = $last_editor ? $last_editor->display_name : 'Unknown';
                $ai_content       = strip_before_dash(trim(get_post_meta($post_id, 'post_kabelkrant_content_gpt', true)));
                $human_content    = strip_before_dash(trim(get_post_meta($post_id, 'post_kabelkrant_content', true)));

                // Determine the status and classes
                if (empty($ai_content)) {
                    $status_label = 'Fully Human Written';
                    $status_class = 'bg-blue-100 text-blue-800';
                } elseif ($ai_content === $human_content) {
                    $status_label = 'AI Written, Not Edited';
                    $status_class = 'bg-red-100 text-red-800';
                } else {
                    $status_label = 'AI Written, Edited';
                    $status_class = 'bg-yellow-100 text-yellow-800';
                }
                ?>
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-2xl font-bold mb-2"><?php echo esc_html($post_title); ?></h3>
                    <div class="text-gray-500 text-sm mb-4">
                        Published on: <?php echo esc_html($post_date); ?> |
                        Author: <?php echo esc_html($author_name); ?> |
                        Last edit: <?php echo esc_html($last_editor_name); ?>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-tight inline-block px-3 py-1 rounded-full <?php echo esc_attr($status_class); ?>">
                        <?php echo esc_html($status_label); ?>
                    </span>

                    <?php if ($status_label === 'Fully Human Written' || $status_label === 'AI Written, Not Edited') : ?>
                        <div class="mt-6">
                            <h4 class="font-bold mb-2">Content:</h4>
                            <p><?php echo nl2br(esc_html($human_content)); ?></p>
                        </div>
                    <?php elseif ($status_label === 'AI Written, Edited') :
                        $diff = generate_word_diff($ai_content, $human_content); ?>
                        <div class="mt-6">
                            <h4 class="font-bold mb-2">Before:</h4>
                            <p><?php echo $diff['before']; ?></p>
                            <h4 class="font-bold mt-4 mb-2">After:</h4>
                            <p><?php echo $diff['after']; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach;
            wp_reset_postdata(); ?>
        </div>
    </div>
    </body>
    </html>
    <?php
}

/**
 * Displays pagination links as a full-width top bar with small text only.
 *
 * @param int $year
 * @param int $month
 */
function display_pagination($year, $month) {
    // Fetch months with posts
    global $wpdb;
    $months_with_posts = $wpdb->get_results("
        SELECT DISTINCT YEAR(p.post_date) AS year, MONTH(p.post_date) AS month
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
        INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
        WHERE pm1.meta_key = 'post_in_kabelkrant'
          AND pm1.meta_value = '1'
          AND pm2.meta_key = 'post_kabelkrant_content_gpt'
          AND pm2.meta_value != ''
          AND p.post_status = 'publish'
        ORDER BY year DESC, month DESC
    ");

    // Build an array of months
    $months_array = [];
    foreach ($months_with_posts as $month_with_post) {
        $months_array[] = [
            'year'  => (int) $month_with_post->year,
            'month' => (int) $month_with_post->month
        ];
    }

    // Find current index
    $current_index = null;
    foreach ($months_array as $index => $month_item) {
        if ($month_item['year'] === $year && $month_item['month'] === $month) {
            $current_index = $index;
            break;
        }
    }

    $previous_month = isset($months_array[$current_index + 1]) ? $months_array[$current_index + 1] : null;
    $next_month     = isset($months_array[$current_index - 1]) ? $months_array[$current_index - 1] : null;

    // Display pagination links as a full-width top bar with small text
    echo '<div class="w-full bg-gray-200 text-sm text-gray-700 py-2 px-4 flex justify-between items-center">';
    if ($previous_month) {
        $prev_url = add_query_arg([
            'year'  => $previous_month['year'],
            'month' => $previous_month['month']
        ]);
        echo '<a href="' . esc_url($prev_url) . '" class="hover:underline">← Previous Month</a>';
    } else {
        echo '<span></span>';
    }

    // Display current month and year
    $current_month_name = date('F Y', mktime(0, 0, 0, $month, 1, $year));
    echo '<span class="text-center">' . esc_html($current_month_name) . '</span>';

    if ($next_month) {
        $next_url = add_query_arg([
            'year'  => $next_month['year'],
            'month' => $next_month['month']
        ]);
        echo '<a href="' . esc_url($next_url) . '" class="hover:underline">Next Month →</a>';
    } else {
        echo '<span></span>';
    }
    echo '</div>';
}
?>
