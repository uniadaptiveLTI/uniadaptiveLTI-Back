<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ServeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'serve:custom';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Guardar la fecha actual en la variable uptime en el archivo .env
        $uptime = now()->toDateTimeString();
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);
        if (strpos($str, 'UPTIME') !== false) {
            // Si la variable UPTIME ya existe, actualizar su valor
            $str = preg_replace('/UPTIME=.*/', "UPTIME={$uptime}", $str);
        } else {
            // Si la variable UPTIME no existe, agregarla al final del archivo
            $str .= "\nUPTIME={$uptime}\n";
        }
        file_put_contents($envFile, $str);

        // Ejecutar el comando serve con el host especificado
        $this->call('serve', ['--host' => '192.168.0.122']);
    }
}
