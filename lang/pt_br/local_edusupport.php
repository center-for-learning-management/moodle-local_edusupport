<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY;;mesmo sem a garantia implícita de;
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   local_edusupport
 * @copyright 2018 Digital Education Society (http://www.dibig.at)
 * @author    Robert Schrenk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string["pluginname"] = 'Ajuda';
$string["edusupport:addinstance"] = 'Adicionar bloco eduSupport';
$string["edusupport:myaddinstance"] = 'Adicionar bloco eduSupport';

$string["archive"] = 'Arquivo';
$string["assigned"] = 'Atribuído';
$string["autocreate_orggroup"] = 'Crie grupos automaticamente para organizações educacionais';
$string["autocreate_usergroup"] = 'Crie automaticamente um grupo privado para o usuário';
$string["be_more_accurate"] = 'Por favor, seja mais preciso ao descrever o seu problema!';
$string["edusupport:canforward2ndlevel"] = 'Pode encaminhar problemas para a equipe de suporte da plataforma';
$string["cachedef_supportmenu"] = 'Cache para o menu de suporte';
$string["changes_saved_successfully"] = 'Alterações salvas com sucesso.';
$string["changes_saved_fail"] = 'As alterações não puderam ser salvas.';
$string["contactphone"] = 'Telefone';
$string["contactphone_missing"] = 'Por favor coloque seu número de telefone';
$string["coursecategorydeletion"] = 'Você está tentando remover uma categoria que contém fóruns de suporte. Certifique-se de desativar os fóruns de suporte primeiro!';
$string["courseconfig"] = 'Configuração do curso';
$string["create_issue"] = 'Entre em contato com o suporte';
$string["create_issue_error_title"] = 'Erro';
$string["create_issue_error_description"] = 'Não foi possível armazenar seu problema!';
$string["create_issue_mail_success_description"] = 'Seu problema foi armazenado. Iremos ajudá-lo o mais rápido possível!';
$string["create_issue_success_title"] = 'Sucesso';
$string["create_issue_success_description"] = 'Seu problema foi armazenado. Iremos ajudá-lo o mais rápido possível!';
$string["create_issue_success_description_mail"] = 'Seu problema foi enviado por correio. Iremos ajudá-lo o mais rápido possível!';
$string["create_issue_success_goto"] = 'ver problema';
$string["create_issue_success_responsibles"] = 'A pessoa de contato para este ticket é/são:';
$string["create_issue_success_close"] = 'perto';
$string["cron:reminder:title"] = 'lembrete eduSupport';
$string["cron:reminder:intro"] = 'Este é um lembrete amigável sobre problemas em aberto, que são atribuídos a você como eduSupporter!';
$string["cron:deleteexpiredissues:title"] = 'excluir problemas expirados';
$string["dedicatedsupporter"] = 'dedicada';
$string["dedicatedsupporter:not_successfully_set"] = 'Apoiador dedicado não pôde ser definido';
$string["dedicatedsupporter:successfully_set"] = 'Suporte dedicado definido com sucesso';
$string["description"] = 'Descrição';
$string["description_missing"] = 'Descrição ausente';
$string["deletethreshhold"] = 'Excluir problemas fechados após';
$string["deletethreshhold:description"] = 'Defina o limite para a exclusão de pendências fechadas na exibição de pendências. Isso afeta apenas a página de problemas, mas não as postagens do fórum. 0 significa manter os problemas fechados para sempre (ainda não recomendado)';
$string["goto_tutorials"] = 'Documentos & Tutoriais';
$string["goto_targetforum"] = 'Fórum de suporte';
$string["edusupport:manage"] = 'Gerenciar';
$string["email_to_xyz"] = 'Enviar e-mail para {$a->email}';
$string["extralinks"] = 'Extralinks';
$string["extralinks:description"] = 'Se você inserir links aqui, o botão "ajuda" será um menu em vez de um botão. Ele incluirá o botão "ajuda" como primeiro elemento e todos os links extras como links adicionais. Insira os links linha por linha no seguinte formato: linkname|url|faicon|target';
$string["faqlink"] = 'FAQ-link';
$string["faqlink:description"] = 'link para perguntas frequentes';
$string["faqread"] = 'alternar leitura de faq';
$string["faqread:description"] = 'Confirmo que li as <a href="{$a}" target="_blank">FAQ</a> antes de postar minha pergunta.';
$string["header"] = 'Pedido de ajuda em <i>{$a}</i>';
$string["holidaymode"] = 'modo feriado';
$string["holidaymode_is_on"] = 'o modo feriado está ativado';
$string["holidaymode_is_on_descr"] = 'Enquanto você estiver de férias, nenhum novo problema será atribuído a você.';
$string["holidaymode_end"] = 'Terminar o modo de férias';
$string["issue"] = 'Questão';
$string["issue:countcurrent"] = 'Problemas em aberto';
$string["issue:countassigned"] = 'problemas assinados';
$string["issue:countother"] = 'Outros problemas';
$string["issue:countclosed"] = 'Problemas encerrados';
$string["issue_assign"] = 'Atribuir problema';
$string["issue_assign_3rdlevel:post"] = '<a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> atribuiu este problema a <a href= "{$a->wwwroot}/user/view.php?id={$a->touuserid}">{$a->touserfullname}</a>.';
$string["issue_assign_3rdlevel:postself"] = '<a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> assumiu a responsabilidade por este problema.';
$string["issue_assign_nextlevel"] = 'Encaminhe para a equipe de suporte {$a->sitename}';
$string["issue_assign_nextlevel:error"] = 'Desculpe, este problema não pôde ser encaminhado para a equipe de suporte da plataforma';
$string["issue_assign_nextlevel:post"] = '<a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> encaminhou este problema para a equipe de suporte da plataforma';
$string["issue_assigned:subject"] = 'Problema de suporte atribuído';
$string["issue_assigned:text"] = 'O problema de suporte foi atribuído a <a href="{$a->wwwroot}/user/view.php?id={$a->id}">{$a->firstname} {$a->lastname} </a>!';
$string["issue_close"] = 'Fechar problema';
$string["issue_closed:subject"] = 'Problema encerrado';
$string["issue_closed:post"] = 'Este problema encerrado foi encerrado por <a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a>. Se precisar de mais assistência, encaminhe este problema novamente para a equipe de suporte da plataforma.';
$string["issue_responsibles:post"] = '<p> A responsabilidade por este problema foi atribuída a: {$a->responsibles}! </p> <p> Os gerentes de sua organização podem encaminhar este problema para o suporte {$a->sitename} clicando no botão "Encaminhar este ticket para o suporte {$a->sitename}" (visível apenas para gerentes no canto superior direito da página). </p>';
$string["issue_responsibles:subject"] = 'Problema atribuído';
$string["issue_revoke"] = 'Revogar este problema do nível de suporte superior';
$string["issue_revoke:error"] = 'Desculpe, este problema não pôde ser revogado dos níveis de suporte mais altos';
$string["issue_revoke:post"] = '<a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> revogou este problema do nível de suporte superior';
$string["issue_revoke:subject"] = 'Problema de suporte revogado';
$string["issues"] = 'questões';
$string["issues:assigned"] = 'Subscrito';
$string["issues:assigned:none"] = 'Atualmente você não tem nenhuma assinatura de emissão';
$string["issues:closed"] = 'Problemas encerrados';
$string["issues:current"] = 'meus problemas';
$string["issues:current:none"] = 'Parece que você merece uma pausa - nenhum problema deixado para o seu!';
$string["issues:other"] = 'Outros problemas';
$string["issues:other:none"] = 'Ótimo, parece não haver mais problemas naquele planeta!';
$string["issues:openmine"] = '{$a} para mim';
$string["issues:opennosupporter"] = '{$a} não atribuído';
$string["issues:openall"] = '{$a} total aberto';
$string["label:2ndlevel"] = 'Equipe de suporte da plataforma';
$string["missing_permission"] = 'Permissão necessária ausente';
$string["missing_targetforum"] = 'Fórum de destino ausente, deve ser configurado!';
$string["missing_targetforum_exists"] = 'O fórum de destino configurado não existe. Configuração errada!';
$string["no_such_issue"] = 'Esta não é uma questão em aberto! Você pode navegar até a <a href="{$a->todiscussionurl}"><u>página de discussão</u></a> ou acessar <a href="{$a->toissuesurl}"><u >voltar à visão geral dos problemas</u></a>.';
$string["only_you"] = 'Somente você e nossa equipe';
$string["phonefield"] = 'desativar campo de telefone';
$string["phonefield:description"] = 'Desative o campo de telefone no formulário para criar problemas';
$string["postto2ndlevel"] = 'Envie para a equipe de suporte da plataforma';
$string["postto2ndlevel:description"] = 'Diretamente para o Suporte {$a->sitename}!';
$string["privacy:metadata"] = 'Este plug-in não armazena nenhum dado pessoal, pois usa um fórum como alvo.';
$string["priority"] = 'definir prioridade';
$string["prioritylvl"] = 'habilitar prioridades';
$string["prioritylvl:description"] = 'Se ativado, você pode selecionar prioridades na lista de problemas';
$string["prioritylvl:low"] = 'baixa prioridade';
$string["prioritylvl:mid"] = 'prioridade média';
$string["prioritylvl:high"] = 'prioridade máxima';
$string["relativeurlsupportarea"] = 'URL relativo à área de suporte';
$string["screenshot"] = 'Postar captura de tela';
$string["screenshot:description"] = 'Uma captura de tela pode ajudar a resolver o problema.';
$string["screenshot:generateinfo"] = 'Para gerar a captura de tela, o formulário será ocultado e reaparecerá posteriormente.';
$string["screenshot:upload:failed"] = 'A preparação do arquivo falhou!';
$string["screenshot:upload:successful"] = 'O arquivo foi preparado com sucesso para upload!';
$string["select_isselected"] = 'Atualmente selecionado';
$string["select_unavailable"] = 'Indisponível';
$string["send"] = 'Mandar';
$string["spamprotection:exception"] = 'Desculpe, a quantidade máxima de problemas foi excedida. Tente novamente em alguns minutos.';
$string["spamprotection:limit"] = 'proteção contra spam > limite';
$string["spamprotection:limit:description"] = 'A quantidade máxima de problemas criados dentro do intervalo de tempo.';
$string["spamprotection:threshold"] = 'Proteção contra spam > minutos';
$string["spamprotection:threshold:description"] = 'O intervalo de tempo usado para proteção contra spam.';
$string["subject"] = 'Sujeito';
$string["subject_missing"] = 'falta de assunto';
$string["support_area"] = 'Suporte técnico e tutoriais';
$string["supportcourse"] = 'curso de suporte';
$string["supporters"] = 'Apoiadores';
$string["supporters:choose"] = 'Escolha apoiadores';
$string["supporters:description"] = 'Todos os usuários do curso, que estiverem inscritos no mínimo como "professor não editor" podem ser configurados como apoiadores. Basta inserir qualquer coisa como nível de suporte para ativar alguém como apoiador!';
$string["supportforum:choose"] = 'Escolha fóruns para eduSupport';
$string["supportforum:central:disable"] = 'desabilitar';
$string["supportforum:central:enable"] = 'habilitar';
$string["supportforum:disable"] = 'desabilitar';
$string["supportforum:enable"] = 'habilitar';
$string["supportlevel"] = 'nível de suporte';
$string["targetforum"] = 'Fórum de suporte';
$string["targetforum:description"] = 'Selecione o fórum que deve ser usado como alvo para problemas de suporte neste curso. Este fórum será forçado a ter algum modo de grupo habilitado. O plug-in criará um grupo individual para cada usuário.';
$string["targetforum:core:description"] = 'Todos os usuários serão inscritos automaticamente no fórum de suporte de todo o sistema assim que criarem um problema de suporte. Além disso, os grupos podem ser criados e gerenciados automaticamente para separar os problemas de suporte.';
$string["to_group"] = 'Para';
$string["toggle"] = 'Fórum de Suporte do Curso';
$string["toggle:central"] = 'Fórum Central de Suporte';
$string["trackhost"] = 'Rastreie o host';
$string["trackhost:description"] = 'Grandes sites moodle podem usar uma arquitetura com vários webhosts. Se você habilitar esta opção, o edusupport adicionará o nome do host usado ao problema.';
$string["userid"] = 'ID do usuário';
$string["userlinks"] = 'ativar links de usuário';
$string["userlinks:description"] = 'mostrar userlinks na lista de problemas';
$string["your_issues"] = 'seus problemas';
$string["webhost"] = 'Hospedeiro';
$string["weburl"] = 'URL';

/* PrivaCY API */
$string["privacy:metadata:edusupport:subscr"] = 'Todos os problemas assinados';
$string["privacy:metadata:edusupport:issues"] = 'Problemas de apoiadores';
$string["privacy:metadata:edusupport:fieldid"] = 'Identidade';
$string["privacy:metadata:edusupport:issueid"] = 'ID do problema';
$string["privacy:metadata:edusupport:discussionid"] = 'ID de discussão do fórum';
$string["privacy:metadata:edusupport:userid"] = 'ID do usuário';
$string["privacy:metadata:edusupport:supporters"] = 'Todos os apoiadores definidos';
$string["privacy:metadata:edusupport:supportlvl"] = 'nível de suporte';
$string["privacy:metadata:edusupport:courseid"] = 'ID do curso com fórum de suporte';
$string["privacy:metadata:edusupport:currentsupporter"] = 'ID do usuário do usuário atribuído';
$string["privacy:metadata:edusupport:opened"] = 'Status do problema';
