<?php

namespace App\Model;

/**
 * Class TranslationsModel.
 */
class TranslationsModel
{
    /** @var array */
    private static $localizedMsg;

    /**
     * @param array  $messages
     * @param array  $data
     * @param string $defaultLang
     *
     * @return array
     */
    public static function localize(array $messages, array $data = [], $defaultLang = 'en')
    {
        $messages = static::localizeMessages(array_merge($messages, $data));
        isset($messages['lang']) ?: $messages['lang'] = $defaultLang;

        return array_merge($messages, ['sizes' => \def::sizes()]);
    }

    /**
     * @param array       $messages
     * @param string|null $lang
     * @param string|null $period
     *
     * @return array
     */
    private static function localizeMessages(array $messages, $lang = null, $period = null)
    {
        $lang = is_null($lang) && isset($messages['lang']) ? $messages['lang'] : $lang;
        $period = is_null($period) && isset($messages['period']) ? $messages['period'] : $period;
        foreach ($messages as $key => $message) {
            if ($key === 'actions'/* && !is_null($period)*/) {
                foreach ($message as $k => $v) {
                    if (strpos($k, $period) === false) {
                        unset($messages[$key][$k]);
                    }
                }
                $messages[$key] = static::localizeMessages($messages[$key], $lang, $period);
            } elseif (is_array($message)) {
                static::$localizedMsg = [];
                foreach ($message as $msg_key => $msg_value) {
                    if (($msg = static::localizeMessage($msg_key, $msg_value, $lang, $period)) !== false) {
                        $messages[$key] = $msg;
                    }
                }
                empty(static::$localizedMsg) ?: $messages[$key] = implode(' / ', static::$localizedMsg);
            } elseif (($msg = static::localizeMessage($key, $message, $lang, $period)) !== false) {
                $messages = $msg;
            }
        }

        return $messages;
    }

    /**
     * @param string       $msg_key
     * @param array|string $msg_value
     * @param string|null  $lang
     * @param string|null  $period
     *
     * @return array|false
     */
    private static function localizeMessage($msg_key, $msg_value, $lang = null, $period = null)
    {
        if ($msg_key === $lang/* && !empty($msg_value)*/) {
            return $msg_value;
        } elseif ($msg_key === $period && is_array($msg_value)) {
            return static::localizeMessages($msg_value, $lang);
        } elseif (is_null($lang) && in_array($msg_key, \def::langISOCodes()) && !empty($msg_value)) {
            static::$localizedMsg[] = $msg_value;
        }

        return false;
    }

    /**
     * @param array       $items
     * @param string      $filename
     * @param string|null $break_table
     * @param string|null $prefix
     *
     * @return array
     */
    private static function parseFields(array $items, $filename = 'index', $break_table = null, $prefix = null)
    {
        $fields = [];
        $config = parseConfig(CONFIG_DIR, $filename);
        foreach ($items as $name => $value) {
            if (array_key_exists($name, $config)) {
                $fields[$name] = static::mapFields($config[$name], $break_table, $prefix);
            }
        }

        return $fields;
    }

    /**
     * @param array       $tables
     * @param string|null $break_table
     * @param string|null $prefix
     *
     * @return array
     */
    private static function mapFields(array $tables, $break_table = null, $prefix = null)
    {
        $fields = [];
        foreach ($tables as $table_name => $table_field) {
            $name = $prefix.$table_name;
            if (is_array($table_field)) {
                $fields = array_merge($fields, static::mapFields($table_field, $break_table, $name));
            } else {
                $fields[$name] = $table_field;
            }
            if ($table_name === $break_table) {
                return $fields;
            }
        }

        return $fields;
    }
}
