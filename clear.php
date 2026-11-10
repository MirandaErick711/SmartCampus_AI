<?php

$action = 'delete';

switch ($action) {
    case 'create':
        echo 'Creazione';
        break;

    case 'update':
        echo 'Aggiornamento';
        break;

    case 'delete':
        echo 'Eliminazione';
        break;

    default:
        echo 'Azione non valida';
        break;
}
