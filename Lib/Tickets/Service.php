<?php
/**
 * Copyright (C) 2019-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Servicios\Lib\Tickets;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Tickets\BaseTicket;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Service extends BaseTicket
{
    public static function print(ModelClass $model, TicketPrinter $printer, User $user, Agente $agent = null): bool
    {
        static::init();

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->title = static::$i18n->trans('service') . ' ' . $model->primaryColumnValue();

        static::setHeader($model, $printer, $ticket->title);
        static::setBody($model, $printer);
        static::setFooter($model, $printer);
        $ticket->body = static::getBody();
        $ticket->base64 = true;
        $ticket->appversion = 1;

        if ($agent) {
            $ticket->codagente = $agent->codagente;
        }

        return $ticket->save();
    }

    protected static function setHeader(ModelClass $model, TicketPrinter $printer, string $title): void
    {
        if ($printer->print_stored_logo) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            // imprimimos el logotipo almacenado en la impresora
            static::$connector->write("\x1Cp\x01\x00\x00");
            static::$escpos->feed();
        }

        // obtenemos los datos de la empresa
        $company = $model->getCompany();

        // establecemos el tamaño de la fuente
        static::$escpos->setTextSize($printer->title_font_size, $printer->title_font_size);

        // imprimimos el nombre corto de la empresa
        if ($printer->print_comp_shortname) {
            static::$escpos->text(static::sanitize($company->nombrecorto) . "\n");
            static::$escpos->setTextSize($printer->head_font_size, $printer->head_font_size);

            // imprimimos el nombre de la empresa
            static::$escpos->text(static::sanitize($company->nombre) . "\n");
        } else {
            // imprimimos el nombre de la empresa
            static::$escpos->text(static::sanitize($company->nombre) . "\n");
            static::$escpos->setTextSize($printer->head_font_size, $printer->head_font_size);
        }

        static::$escpos->setJustification();

        // imprimimos la dirección de la empresa
        static::$escpos->text(static::sanitize($company->direccion) . "\n");
        static::$escpos->text(static::sanitize("CP: " . $company->codpostal . ', ' . $company->ciudad) . "\n");
        static::$escpos->text(static::sanitize($company->tipoidfiscal . ': ' . $company->cifnif) . "\n\n");

        if ($printer->print_comp_tlf) {
            if (false === empty($company->telefono1) && false === empty($company->telefono2)) {
                static::$escpos->text(static::sanitize($company->telefono1 . ' / ' . $company->telefono2) . "\n");
            } elseif (false === empty($company->telefono1)) {
                static::$escpos->text(static::sanitize($company->telefono1) . "\n");
            } elseif (false === empty($company->telefono2)) {
                static::$escpos->text(static::sanitize($company->telefono2) . "\n");
            }
        }

        // imprimimos el título del documento
        
        static::$escpos->setTextSize($printer->title_font_size, $printer->title_font_size);
        static::$escpos->text(static::sanitize($title) . "\n");
        static::$escpos->setTextSize($printer->head_font_size, $printer->head_font_size);
        
        static::setHeaderTPV($model, $printer);

        // si es un documento de venta
        // imprimimos la fecha y el cliente
        if (in_array($model->modelClassName(), ['PresupuestoCliente', 'PedidoCliente', 'AlbaranCliente', 'FacturaCliente'])) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('date') . ': ' . $model->fecha . ' ' . $model->hora) . "\n");
            static::$escpos->text(static::sanitize(static::$i18n->trans('customer') . ': ' . $model->nombrecliente) . "\n\n");
        }

        // añadimos la cabecera
        if ($printer->head) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            static::$escpos->text(static::sanitize($printer->head) . "\n\n");
            static::$escpos->setJustification();
        }
    }

    protected static function setBody(ModelClass $model, TicketPrinter $printer): void
    {
        static::$escpos->setTextSize($printer->font_size, $printer->font_size);

        static::$escpos->text(static::sanitize(static::$i18n->trans('date') . ': ' . $model->fecha . ' ' . $model->hora) . "\n");

        $customer = $model->getCustomer();
        static::$escpos->text(static::sanitize(static::$i18n->trans('customer') . ': ' . $customer->razonsocial) . "\n");
        if ($customer->telefono1) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('phone') . ': ' . $customer->telefono1) . "\n\n");
        }
        if ($customer->telefono2) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('phone') . ': ' . $customer->telefono2) . "\n\n");
        }

        static::$escpos->text(static::sanitize(static::$i18n->trans('description') . ': ' . $model->descripcion) . "\n");

        if ($model->material) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('material') . ': ' . $model->material) . "\n");
        }
    }

    protected static function setFooter(ModelClass $model, TicketPrinter $printer): void
    {
        parent::setFooter($model, $printer);

        // si hay un texto personalizado de pie de ticket, lo añadimos
        if (false === empty(Tools::settings('servicios', 'footertext'))) {
            static::$escpos->text("\n" . static::sanitize(Tools::settings('servicios', 'footertext')) . "\n");
        }
    }
}
