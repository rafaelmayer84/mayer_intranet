<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        if (DB::table('sisrh_rubricas')->count() > 0) return;
        $now = now();
        DB::table('sisrh_rubricas')->insert([
            ['codigo'=>'001','nome'=>'Proventos (Pró-labore)','tipo'=>'provento','automatica'=>1,'formula'=>'rb','ativo'=>1,'ordem'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['codigo'=>'002','nome'=>'Remuneração Variável (RV)','tipo'=>'provento','automatica'=>1,'formula'=>'rv','ativo'=>1,'ordem'=>2,'created_at'=>$now,'updated_at'=>$now],
            ['codigo'=>'003','nome'=>'Gratificação de Função','tipo'=>'provento','automatica'=>0,'formula'=>null,'ativo'=>1,'ordem'=>3,'created_at'=>$now,'updated_at'=>$now],
            ['codigo'=>'004','nome'=>'Bônus','tipo'=>'provento','automatica'=>0,'formula'=>null,'ativo'=>1,'ordem'=>4,'created_at'=>$now,'updated_at'=>$now],
            ['codigo'=>'005','nome'=>'Ajuste Provento','tipo'=>'provento','automatica'=>0,'formula'=>null,'ativo'=>1,'ordem'=>5,'created_at'=>$now,'updated_at'=>$now],
            ['codigo'=>'045','nome'=>'INSS (Contribuinte Individual)','tipo'=>'desconto','automatica'=>1,'formula'=>'inss','ativo'=>1,'ordem'=>10,'created_at'=>$now,'updated_at'=>$now],
            ['codigo'=>'900','nome'=>'PAMS','tipo'=>'desconto','automatica'=>0,'formula'=>null,'ativo'=>1,'ordem'=>11,'created_at'=>$now,'updated_at'=>$now],
            ['codigo'=>'901','nome'=>'Co-participação','tipo'=>'desconto','automatica'=>0,'formula'=>null,'ativo'=>1,'ordem'=>12,'created_at'=>$now,'updated_at'=>$now],
            ['codigo'=>'902','nome'=>'Adiantamento','tipo'=>'desconto','automatica'=>0,'formula'=>null,'ativo'=>1,'ordem'=>13,'created_at'=>$now,'updated_at'=>$now],
            ['codigo'=>'903','nome'=>'Ajuste Desconto','tipo'=>'desconto','automatica'=>0,'formula'=>null,'ativo'=>1,'ordem'=>14,'created_at'=>$now,'updated_at'=>$now],
        ]);
    }
    public function down(): void {
        DB::table('sisrh_rubricas')->whereIn('codigo',['001','002','003','004','005','045','900','901','902','903'])->delete();
    }
};
