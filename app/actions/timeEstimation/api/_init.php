<?php

if ($_SERVER['HTTP_API_KEY'] !== TE_API_KEY) {
    error('Invalid API-Key');
}
