@extends('layout.main-public')

@section('page-title', 'Exclusão de Conta')

@section('content')
    <p class="text-xs text-muted-foreground">Última atualização: {{ now()->translatedFormat('d \d\e F \d\e Y') }}</p>

    <section>
        <h2>Como excluir sua conta no {{ env('APP_NAME') }}</h2>
        <p>
            Você pode excluir sua conta do {{ env('APP_NAME') }} a qualquer momento, pelo aplicativo
            Android ou pelo site. A exclusão é imediata e não pode ser desfeita.
        </p>
    </section>

    <section>
        <h2>Pelo aplicativo</h2>
        <ol>
            <li>Abra o {{ env('APP_NAME') }} e entre na sua conta;</li>
            <li>Toque na aba <strong>Conta</strong> e depois no cartão com seu nome, que abre <strong>Minha Conta</strong>;</li>
            <li>Role até a seção <strong>Excluir conta</strong>;</li>
            <li>Informe sua senha atual (não é necessário para contas criadas com o Google);</li>
            <li>Toque em <strong>Excluir minha conta</strong> e confirme.</li>
        </ol>
    </section>

    <section>
        <h2>Pelo site</h2>
        <ol>
            <li>Acesse <a class="link" href="{{ route('login.index') }}">{{ config('app.url') }}</a> e entre na sua conta;</li>
            <li>Abra <strong>Minha Conta</strong> e depois <strong>Segurança</strong>;</li>
            <li>Clique em <strong>Excluir conta</strong>, informe sua senha atual e confirme.</li>
        </ol>
    </section>

    <section>
        <h2>Sem acesso à conta?</h2>
        <p>
            Envie um e-mail para <a class="link" href="mailto:contato@cestazen.com.br">contato@cestazen.com.br</a>
            a partir do endereço cadastrado, com o assunto "Exclusão de conta". A solicitação é atendida em até
            15 dias.
        </p>
    </section>

    <section id="dados">
        <h2>Excluir apenas alguns dados, sem encerrar a conta</h2>
        <p>
            O {{ env('APP_NAME') }} permite apagar parte dos seus dados mantendo a conta ativa.
        </p>
        <h3>Diretamente no aplicativo ou no site</h3>
        <ol>
            <li>Entre na sua conta;</li>
            <li>Abra a tela do dado que deseja remover (Listas de compras, Orçamentos, Categorias ou Favoritos);</li>
            <li>Use a opção de excluir do item. A remoção é imediata e definitiva.</li>
        </ol>
        <h3>Por solicitação</h3>
        <ol>
            <li>Envie um e-mail para <a class="link" href="mailto:contato@cestazen.com.br">contato@cestazen.com.br</a>
                a partir do endereço cadastrado, com o assunto "Exclusão de dados";</li>
            <li>Informe quais dados deseja remover (por exemplo, notas fiscais importadas, dados de localização ou foto de perfil);</li>
            <li>A solicitação é atendida em até 15 dias e você recebe a confirmação por e-mail.</li>
        </ol>
        <h3>O que é excluído e o que é mantido</h3>
        <ul>
            <li>Listas, orçamentos, categorias, favoritos, foto e dados de localização são removidos permanentemente;</li>
            <li>Notas fiscais importadas são desvinculadas da sua conta e ficam anonimizadas no histórico agregado de preços, sem qualquer identificação sua;</li>
            <li>Dados de cadastro (nome, e-mail, senha) são mantidos enquanto a conta existir;</li>
            <li>Não há período adicional de retenção: os dados removidos deixam de existir no momento da exclusão.</li>
        </ul>
    </section>

    <section>
        <h2>O que é excluído</h2>
        <ul>
            <li>Dados de cadastro (nome, e-mail, senha, vínculo com login social);</li>
            <li>Perfil (cidade, estado, localização), foto e assinatura;</li>
            <li>Categorias, orçamentos, listas de compras, favoritos e notificações;</li>
            <li>Tokens de acesso e arquivos exportados.</li>
        </ul>
    </section>

    <section>
        <h2>O que é mantido</h2>
        <p>
            As notas fiscais que você importou são <strong>anonimizadas</strong>: o vínculo com a sua conta é
            removido de forma permanente, mas os dados de produto, preço e emitente continuam no histórico
            agregado de preços que alimenta a comparação entre mercados. Esses registros não permitem
            identificar você. Não há período adicional de retenção dos seus dados pessoais após a exclusão.
        </p>
        <p>
            Detalhes completos na <a class="link" href="{{ route('legal.privacy') }}">Política de Privacidade</a>.
        </p>
    </section>
@endsection
