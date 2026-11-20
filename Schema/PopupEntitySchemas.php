<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Schema;

use DbCms\Core\Content\EntitySchema;
use DbCms\Modules\Popups\Models\Popup;
use DbCms\Modules\Popups\Services\PopupEditorialPreview;
use DbCms\Modules\Popups\Services\PopupMutationHandler;

final class PopupEntitySchemas
{
    public static function popup(): EntitySchema
    {
        return new EntitySchema(
            type: 'popup', label: 'Popup', model: Popup::class, sectionable: true,
            description: 'Aviso con contenido por bloques, condiciones y disparadores. No tiene URL pública independiente.',
            permission: 'manage:popups', ownerModule: 'popups', searchable: false,
            mutationHandler: PopupMutationHandler::class, previewHandler: PopupEditorialPreview::class,
            createView: 'popups::admin.create', editView: 'popups::admin.edit', adminFormView: 'popups::admin._form',
            creationDefaults: ['mode' => 'modal', 'priority' => 0, 'configuration' => ['triggers' => [['type' => 'delay', 'seconds' => 3]], 'frequency' => ['mode' => 'page']]],
            fields: [
                EntitySchema::ROLE_TITLE => ['input' => 'translations[{locale}][title]', 'label' => 'Título accesible', 'required' => true],
                EntitySchema::ROLE_SLUG => ['input' => 'translations[{locale}][slug]', 'label' => 'Identificador editorial'],
            ],
        );
    }
}
