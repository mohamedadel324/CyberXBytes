<?php

namespace App\Services;

use App\Models\EmailTemplate;

class EmailTemplateService
{
    /**
     * Get the email template by type
     *
     * @param string $type
     * @return EmailTemplate|null
     */
    public function getTemplate(string $type): ?EmailTemplate
    {
        return EmailTemplate::where('type', $type)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get the subject for a specific template type
     *
     * @param string $type
     * @param string $defaultSubject
     * @return string
     */
    public function getSubject(string $type, string $defaultSubject): string
    {
        $template = $this->getTemplate($type);
        
        return $template ? $template->subject : $defaultSubject;
    }

    /**
     * Get the header text for a specific template type
     *
     * @param string $type
     * @param string $defaultHeaderText
     * @return string
     */
    public function getHeaderText(string $type, string $defaultHeaderText): string
    {
        $template = $this->getTemplate($type);
        
        return $template ? $template->header_text : $defaultHeaderText;
    }
} 