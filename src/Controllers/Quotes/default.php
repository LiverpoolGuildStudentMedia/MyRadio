<?php

CoreUtils::getTemplateObject()->setTemplate('table.twig')
    ->addVariable('tablescript', 'myury.datatable.default')
    ->addVariable('title', 'Quotes')
    ->addVariable('tabledata', ServiceAPI::setToDataSource(MyRadio_Quote::getAll()))
    ->addVariable('tablescript', 'myradio.quotes')
    ->render();
