<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Services;

use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Security\Contracts\CookieDisclosureContributorInterface;
use DbCms\Modules\Popups\Models\Popup;

/** Discloses only storage actually configured by an enabled, published popup. */
final class PopupCookieDisclosure implements CookieDisclosureContributorInterface
{
    public function __construct(private readonly ModuleManager $modules) {}

    /**
     * @return array<string,array{key:string,title:string,description:string,required:bool,default:bool,cookies:array<int,array{name:string,provider:string,purpose:string,duration:string}>}> */
    public function categories(string $locale): array
    {
        $categories = [];
        foreach ($this->frequencies() as $frequency) {
            $purpose = (string) $frequency['purpose'];
            $english = $locale === 'en';
            $categories[$purpose] ??= [
                'key' => $purpose,
                'title' => match ($purpose) {
                    'analytics' => $english ? 'Analytics' : 'Análisis', 'marketing' => 'Marketing', default => $english ? 'Preferences' : 'Preferencias'
                },
                'description' => $english ? 'Stores the time an announcement was shown in this browser to limit repeat appearances.' : 'Guarda en este navegador cuándo se mostró un aviso para limitar sus repeticiones.',
                'required' => false, 'default' => false, 'cookies' => [],
            ];
            $categories[$purpose]['cookies'][] = [
                'name' => 'dbcms.popup.v1.'.$frequency['id'], 'provider' => 'dbCMS (Web Storage)',
                'purpose' => $english ? 'Limit repeat announcement appearances.' : 'Limitar la repetición de avisos.',
                'duration' => $frequency['mode'] === 'session' ? ($english ? 'Tab session' : 'Sesión de pestaña') : ((string) $frequency['seconds'].' s'),
            ];
        }

        return $categories;
    }

    /**
     * @return array<string,string> */
    public function revisionData(): array
    {
        $frequencies = $this->frequencies();

        return $frequencies === [] ? [] : ['frequency' => hash('sha256', json_encode($frequencies, JSON_THROW_ON_ERROR))];
    }

    /**
     * @return list<array{id:int,purpose:string,mode:string,seconds:int}> */
    private function frequencies(): array
    {
        if (! $this->modules->isModuleEnabled('popups')) {
            return [];
        }
        $frequencies = [];
        foreach (Popup::query()->published()->orderBy('id')->get(['id', 'configuration']) as $popup) {
            $frequency = (array) ($popup->configuration['frequency'] ?? []);
            if (! in_array($frequency['mode'] ?? 'page', ['session', 'once', 'cooldown'], true)
                || ! in_array($frequency['purpose'] ?? null, ['preferences', 'analytics', 'marketing'], true)) {
                continue;
            }
            $frequencies[] = ['id' => $popup->id, 'purpose' => (string) $frequency['purpose'], 'mode' => (string) $frequency['mode'], 'seconds' => ($frequency['mode'] === 'cooldown' ? (int) ($frequency['seconds'] ?? 0) : 31536000)];
        }

        return $frequencies;
    }
}
