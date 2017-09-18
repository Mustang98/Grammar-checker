<?php
// Establishing connection with MySQL
$database = new mysqli("127.0.0.1:3306", "grammarc", "y392s0fzYK", "grammarc_vocabulary");
$database->set_charset("UTF8");

// All symbols in vocabulary sorted by descending frequency in russian language
$possible_symbols = mb_string_to_symbols("оеаинтсрвлкмдпуяыьгбзчйхжшюцщэф-ъё");

// Checks whether the word exist in vocabulary or not
// If word exist, it's frequency in russian language is returned.
function word_exist(&$word) {
    global $database;
	$query = $database->query("SELECT * FROM words WHERE word='".$word."';"); 
    if (mysqli_num_rows($query) > 0) {
        return intval(mysqli_fetch_assoc($query)["frequency"]); 
    } else {
        return 0;
    }
}

// Returns how many words with a given prefix are in vocabulary
function count_words_by_prefix(&$prefix) {
    global $database;
    $query = $database->query("SELECT COUNT(*) AS number FROM words WHERE word LIKE '".$prefix."%';"); 
    return intval(mysqli_fetch_assoc($query)["number"]);
}

// Returns all words with a given prefix
function get_words_by_prefix(&$prefix) {
    $query = $database->query("SELECT * FROM words WHERE word LIKE '".$prefix."%';"); 
    $word_number = mysqli_num_rows($query);

    $words = [];
    for ($i = 0; $i < $word_number; $i++) { 
	 	 $row = mysqli_fetch_assoc($query);
		 $words[] = $row["word"];
    }
    
    return $words;
}

// Checks if the word can be obtained by merging two words together
// Returns separating index (the first index of the second word) or -1 if such index wasn't found
function find_split($word) {
    $word_length = mb_strlen($word);
    for ($prefix_length = 1; $prefix_length < $word_length; ++$prefix_length) {
        $prefix = mb_substr($word, 0, $prefix_length);
        $suffix = mb_substr($word, $prefix_length);
        if (word_exist($prefix) && word_exist($suffix)) {
            return $prefix_length;
        }
    }
    return -1;
}

// Generates all words that can be obtained from the current with one change (deletion, insertion,
// replacement or transposition).
// If parameter $find_existing is true, generated words are being checked for existing and once the existing 
// word found, it's being returned. If there aren't such words, function returns empty string.
// At the other hand, if this parameter is false, all generated words are saved to $edited_words array without checking.
function generate_edits($word, // source word 
                        $from_index, // start index for applying edits
                        $find_existing, // find the first existing edit or save all edits without checking for existence
                        &$edited_words) { // array with generated words. Key -> word, value -> edited index + 1
    global $possible_symbols;
    $symbols = mb_string_to_symbols($word);
    $length = count($symbols);
    $prefixes = [""]; // prefixes[i] = word[0; i)
    $suffixes = []; // suffixes[i] = word[i; end]
    for ($i = 0; $i < $length; ++$i) {
        $prefixes[] = mb_substr($word, 0, $i + 1);
        $suffixes[] = mb_substr($word, $i);
    }
    $suffixes[] = "";
    
    // Remove symbol $i
    for ($i = $from_index; $i < $length; ++$i) {
        // All further words will start from this prefix, therefore if there aren't words with
        if (count_words_by_prefix($prefixes[$i]) == 0) { // such prefix we can break
            break;
        }
        $new_word = $prefixes[$i].$suffixes[$i + 1];
        
        if ($find_existing) {
            if (word_exist($new_word)) {
                return array("word" => $new_word,
                             "edited_index" => -1); // "edited_index" - index of edited symbol in "word"
            } 
        } else if (array_key_exists($new_word, $edited_words)) {
            $edited_words[$new_word] = min($edited_words[$new_word], $i);
        } else {
            $edited_words[$new_word] = $i;
        }
    }
    
    // Add one symbol before $i
    // There we try to add popular symbols firstly to find the correct word earlier
    foreach ($possible_symbols as $symbol) {
        for ($i = $from_index; $i <= $length; ++$i) {
            if (count_words_by_prefix($prefixes[$i]) == 0) {
                break;
            }
            $new_word = $prefixes[$i].$symbol.$suffixes[$i]; 
            
            if ($find_existing) {
                if (word_exist($new_word)) {
                    return array("word" => $new_word,
                                 "edited_index" => $i);
                } 
            } else if (array_key_exists($new_word, $edited_words)) {
                $edited_words[$new_word] = min($edited_words[$new_word], $i + 1);
            } else {
                $edited_words[$new_word] = $i + 1;
            }
        }
    }
    
    // Replace current symbol with some other
    foreach ($possible_symbols as $symbol) {
        for ($i = $from_index; $i < $length; ++$i) {
            if ($symbols[$i] == $symbol) {
                continue;
            }
            if (count_words_by_prefix($prefixes[$i]) == 0) {
                break;
            }
            $new_word = $prefixes[$i].$symbol.$suffixes[$i + 1]; 
            
            if ($find_existing) {
                if (word_exist($new_word)) {
                    return array("word" => $new_word,
                                 "edited_index" => $i);
                } 
            } else if (array_key_exists($new_word, $edited_words)) {
                $edited_words[$new_word] = min($edited_words[$new_word], $i + 1);
            } else {
                $edited_words[$new_word] = $i + 1;
            }
        } 
    }
         
    // Transpose current symbol and the next one
    for ($i = 0; $i < $length - 1; ++$i) { 
        if (count_words_by_prefix($prefixes[$i]) == 0) {
            break;
        }
        $new_word = $prefixes[$i].$symbols[$i + 1].$symbols[$i].$suffixes[$i + 2];
        
        if ($find_existing) {
            if (word_exist($new_word)) {
                return array("word" => $new_word,
                             "edited_index" => $i + 1);
            } 
        } else if (array_key_exists($new_word, $edited_words)) {
            $edited_words[$new_word] = min($edited_words[$new_word], $i + 2);
        } else {
            $edited_words[$new_word] = $i + 2;
        }
    }
    return NULL;
} 

// Returns an array of changed indexes in $edited_words (to highlight them)
function restore_edit($initial_word, $edited_word, $edit_index) {
    $changed_indexes = [];
    $initial_length = mb_strlen($initial_word);
    $edited_length = mb_strlen($edited_word);
    if ($initial_length < $edited_length) { // insertion
        $changed_indexes[] = $edit_index;
    } else  if ($initial_length == $edited_length) {
        if ($edit_index == 0 || 
            mb_substr($initial_word, $edit_index - 1, 1) == mb_substr($edited_word, $edit_index - 1, 1)) { // replacement
            $changed_indexes[] = $edit_index;
        } else { // transposition
            $changed_indexes[] = $edit_index;
            $changed_indexes[] = $edit_index - 1;
        }
    }
    return $changed_indexes;
}

// Converts substring of $word to uppercase
function mb_substr_to_upper(&$word, $start_index, $length) {
    $new_word = mb_substr($word, 0, $start_index).
                mb_strtoupper(mb_substr($word, $start_index, $length)).
                mb_substr($word, $start_index + $length);
    $word = $new_word;
}

// Tries to find the most probable correct word that could be intended
function find_replacement($word, &$is_corrected) {
    $edited_words = [];
    // Generating all edits at distance 1 from initial word
    generate_edits($word, 0, false, $edited_words);
    
    $corrected_word = "";
    $correction_index;
    $best_frequency = 0;
    
    // Finding the most frequent word among all existing words with distance 1 from the initial
    foreach ($edited_words as $edited_word => &$next_index) {
        $current_frequency = word_exist($edited_word);
        if ($current_frequency > $best_frequency) {
            $best_frequency = $current_frequency;
            $corrected_word = $edited_word;
            $correction_index = $next_index - 1;
        }
    }
    
    // If an existing word is found, the edits are being restored and highlighted (changed to uppercase)
    if ($best_frequency != 0) {
        $is_corrected = true;
        $changed_indexes = restore_edit($word, $corrected_word, $correction_index);
        foreach ($changed_indexes as $index) {
            mb_substr_to_upper($corrected_word, $index, 1);
        }
        return $corrected_word;
    }
    
    // Checking if the word is obtained by merging two words together
    $split_index = find_split($word);
    if ($split_index != -1) {
        $is_corrected = true;
        return mb_substr($word, 0, $split_index)." ".mb_substr($word, $split_index);
    }
    //$is_corrected = true; /////////////

    // Finding any existing word with distance 2 from the initial
    foreach ($edited_words as $edited_word => &$next_index) { // It makes sence to do the following edit only to the right of the previous one.
        if ($next_index > mb_strlen($edited_word) || // Therefore if we can't make any edit to the right
            count_words_by_prefix(mb_substr($edited_word, 0, $next_index)) == 0) { // or there isn't any word with the current prefix
            continue; // we can skip this word.
        }
        
        // Find any existing word.
        $edited_words2 = [];
        $search_result = generate_edits($edited_word, $next_index, true, $edited_words2);
        if ($search_result == NULL) {
            continue;
        }
        
        // Restoring edited symbols from both edits
        $changed_indexes1 = restore_edit($word, $edited_word, $next_index - 1);
        $changed_indexes2 = restore_edit($edited_word, $search_result["word"], $search_result["edited_index"]);
        $changed_indexes = array_merge($changed_indexes1, $changed_indexes2);
        $corrected_word = $search_result["word"];
        foreach ($changed_indexes as $index) {
            mb_substr_to_upper($corrected_word, $index, 1);
        }
        $is_corrected = true;
        return $corrected_word;
    }
    
    $is_corrected = false;
}
?>