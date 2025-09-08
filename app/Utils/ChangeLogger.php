<?php

namespace App\Utils;

class ChangeLogger
{
    /**
     * Format changes for logging with old and new values
     */
    public static function formatChanges(array $originalValues, array $newValues): array
    {
        $changes = [];
        
        foreach ($newValues as $field => $newValue) {
            $originalValue = $originalValues[$field] ?? null;
            
            // Only log if values are actually different
            if ($originalValue !== $newValue) {
                $changes[$field] = [
                    'from' => $originalValue,
                    'to' => $newValue
                ];
            }
        }
        
        return $changes;
    }

    /**
     * Format changes with additional context for comprehensive logging
     */
    public static function formatChangesWithContext(
        array $originalValues, 
        array $newValues, 
        array $additionalContext = []
    ): array {
        $changes = self::formatChanges($originalValues, $newValues);
        
        return array_merge([
            'changes' => $changes,
            'change_count' => count($changes),
            'fields_changed' => array_keys($changes)
        ], $additionalContext);
    }

    /**
     * Log update operation with comprehensive change tracking
     */
    public static function logUpdate(
        string $logLevel,
        string $message,
        array $originalValues,
        array $newValues,
        array $context = []
    ): void {
        $changeData = self::formatChangesWithContext($originalValues, $newValues, $context);
        
        \Illuminate\Support\Facades\Log::{$logLevel}($message, $changeData);
    }

    /**
     * Log successful update with change details
     */
    public static function logSuccessfulUpdate(
        string $message,
        array $originalValues,
        array $newValues,
        array $context = []
    ): void {
        self::logUpdate('info', $message, $originalValues, $newValues, $context);
    }

    /**
     * Log update attempt with change details
     */
    public static function logUpdateAttempt(
        string $message,
        array $originalValues,
        array $newValues,
        array $context = []
    ): void {
        self::logUpdate('info', $message, $originalValues, $newValues, $context);
    }

    /**
     * Check if there are any actual changes
     */
    public static function hasChanges(array $originalValues, array $newValues): bool
    {
        return !empty(self::formatChanges($originalValues, $newValues));
    }

    /**
     * Get only the fields that actually changed
     */
    public static function getChangedFields(array $originalValues, array $newValues): array
    {
        return array_keys(self::formatChanges($originalValues, $newValues));
    }

    /**
     * Get change summary for display
     */
    public static function getChangeSummary(array $originalValues, array $newValues): string
    {
        $changes = self::formatChanges($originalValues, $newValues);
        
        if (empty($changes)) {
            return 'No changes detected';
        }
        
        $summary = [];
        foreach ($changes as $field => $change) {
            $summary[] = "{$field}: '{$change['from']}' → '{$change['to']}'";
        }
        
        return implode(', ', $summary);
    }
}
