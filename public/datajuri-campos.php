<?php
function getCamposForModule(string $mod): string {
    $map = [
        'Processo' => 'id,pasta,numero,status,tipoAcao,tipoProcesso,natureza,assunto,valorCausa,valorProvisionado,valorSentenca,possibilidade,ganhoCausa,tipoEncerramento,proprietario.nome,proprietario.id,cliente.nome,clienteId,cliente.numeroDocumento,adverso.nome,adversoId,posicaoCliente,posicaoAdverso,advogadoCliente.nome,advogadoClienteId,faseAtual.numero,faseAtual.vara,faseAtual.instancia,faseAtual.orgao.nome,dataAbertura,dataDistribuicao,dataEncerrado,observacao,dataCadastro',
        'Movimento' => 'id,data,valor,valorComSinal,tipo,descricao,observacao,planoConta.nomeCompleto,planoConta.codigo,planoConta.id,pessoa.nome,pessoa.id,contrato.id,contrato.numero,processo.pasta,processo.id,proprietario.nome,proprietario.id,formaPagamento,conciliado,relativoa,contaId,dataCadastro',
        'ContasReceber' => 'id,descricao,valor,dataVencimento,dataPagamento,prazo,tipo,pessoa.nome,pessoaId,cliente.nome,clienteId,processo.pasta,processoId,contrato.numero,contratoId,observacao,dataCadastro',
        'Contrato' => 'id,numero,valor,tipo,status,dataAssinatura,dataVencimento,contratante.nome,contratante.id,contratante.numeroDocumento,proprietario.nome,proprietario.id,processo.pasta,processo.id,observacao,dataCadastro',
        'HoraTrabalhada' => 'id,data,duracao,totalHoraTrabalhada,horaInicial,horaFinal,valorHora,valorTotal,assunto,tipo,status,processo.pasta,processo.id,proprietario.id,proprietario.nome,particular,dataFaturado,dataCadastro',
        'Atividade' => 'id,status,dataHora,dataConclusao,dataPrazoFatal,descricao,processo.pasta,processo.id,proprietario.id,proprietario.nome,particular,dataCadastro',
        'Pessoa' => 'id,nome,email,outroEmail,telefone,celular,numeroDocumento,cpf,cnpj,tipoPessoa,statusPessoa,cliente,enderecoprua,enderecopnumero,enderecopbairro,enderecopcidade,enderecopestado,dataNascimento,profissao,proprietario.nome,proprietarioId,codigoPessoa,valorHora,dataCadastro',
        'Fase' => 'id,processo.pasta,processo.id,tipoFase,localidade,instancia,data,faseAtual,diasFaseAtiva,dataUltimoAndamento,proprietario.nome,proprietario.id',
        'AndamentoFase' => 'id,faseProcesso.id,processo.id,processo.pasta,dataAndamento,descricao,tipo,parecer,proprietario.id,proprietario.nome',
    ];
    return $map[$mod] ?? '';
}
