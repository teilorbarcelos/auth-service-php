<?php

declare(strict_types=1);

namespace App\Infrastructure\Email;

class EmailTemplates
{
    public const WELCOME_TEMPLATE = <<<'HTML'
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;">
  <h2 style="color: #333;">Bem-vindo ao sistema, {{name}}!</h2>
  <p style="color: #555;">Sua conta foi criada com sucesso. Aqui estão seus dados de acesso:</p>
  <div style="background-color: #f9f9f9; padding: 15px; border-radius: 4px; margin: 20px 0;">
    <p style="margin: 5px 0;"><strong>E-mail:</strong> {{email}}</p>
    <p style="margin: 5px 0;"><strong>Senha Inicial:</strong> <span style="color: #d9534f; font-weight: bold;">{{password}}</span></p>
  </div>
  <p style="color: #555;">Recomendamos que você altere sua senha após o primeiro acesso.</p>
  <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
  <p style="font-size: 12px; color: #999;">Esta é uma mensagem automática, por favor não responda.</p>
</div>
HTML;

    public const FORGOT_PASSWORD_TEMPLATE = <<<'HTML'
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;">
  <h2 style="color: #333;">Recuperação de Senha</h2>
  <p style="color: #555;">Olá {{name}},</p>
  <p style="color: #555;">Recebemos uma solicitação para redefinir a sua senha. Utilize o código abaixo para prosseguir:</p>
  <div style="background-color: #f9f9f9; padding: 15px; border-radius: 4px; text-align: center; margin: 20px 0;">
    <h1 style="letter-spacing: 5px; color: #007bff; margin: 0;">{{token}}</h1>
  </div>
  <p style="color: #555;">Se você não solicitou isso, pode ignorar este e-mail com segurança.</p>
  <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
  <p style="font-size: 12px; color: #999;">O código expira em 15 minutos.</p>
</div>
HTML;

    /**
     * @param string $template
     * @param array<string, mixed> $context
     * @return string
     */
    public static function render(string $template, array $context): string
    {
        foreach ($context as $key => $value) {
            $stringValue = is_scalar($value) ? (string)$value : '';
            $template = str_replace('{{' . $key . '}}', $stringValue, $template);
        }
        return $template;
    }
}
