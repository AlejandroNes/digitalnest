<?php

namespace App;

enum Permission: string
{
    case ViewRedirect = 'redirects.view';
    case CreateRedirect = 'redirects.create';
    case UpdateRedirect = 'redirects.update';
    case DeleteRedirect = 'redirects.delete';

    case ViewContactMessage = 'contact_messages.view';
    case UpdateContactMessage = 'contact_messages.update';
    case DeleteContactMessage = 'contact_messages.delete';

    case ViewContactAssistantSettings = 'contact_assistant.view';
    case UpdateContactAssistantSettings = 'contact_assistant.update';

    case TestMail = 'mail.test';

    case ViewUsers = 'users.view';
    case CreateUsers = 'users.create';
    case UpdateUsers = 'users.update';
    case DeleteUsers = 'users.delete';

    case ViewSettings = 'settings.view';
    case UpdateSettings = 'settings.update';

    case ViewMediaAsset = 'media.view';
    case CreateMediaAsset = 'media.create';
    case UpdateMediaAsset = 'media.update';
    case DeleteMediaAsset = 'media.delete';

    case ViewPostCategory = 'categories.view';
    case CreatePostCategory = 'categories.create';
    case UpdatePostCategory = 'categories.update';
    case DeletePostCategory = 'categories.delete';

    case ViewPost = 'posts.view';
    case CreatePost = 'posts.create';
    case UpdatePost = 'posts.update';
    case DeletePost = 'posts.delete';

    case ViewService = 'services.view';
    case CreateService = 'services.create';
    case UpdateService = 'services.update';
    case DeleteService = 'services.delete';

    case ViewTestimonial = 'testimonials.view';
    case CreateTestimonial = 'testimonials.create';
    case UpdateTestimonial = 'testimonials.update';
    case DeleteTestimonial = 'testimonials.delete';

    case ViewFaq = 'faqs.view';
    case CreateFaq = 'faqs.create';
    case UpdateFaq = 'faqs.update';
    case DeleteFaq = 'faqs.delete';

    case ViewLegalPage = 'legal_pages.view';
    case CreateLegalPage = 'legal_pages.create';
    case UpdateLegalPage = 'legal_pages.update';
    case DeleteLegalPage = 'legal_pages.delete';

    case ViewProjectCategory = 'project_categories.view';
    case CreateProjectCategory = 'project_categories.create';
    case UpdateProjectCategory = 'project_categories.update';
    case DeleteProjectCategory = 'project_categories.delete';

    case ViewProject = 'projects.view';
    case CreateProject = 'projects.create';
    case UpdateProject = 'projects.update';
    case DeleteProject = 'projects.delete';

    public function module(): string
    {
        return explode('.', $this->value)[0];
    }

    public function action(): string
    {
        return explode('.', $this->value)[1];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ViewRedirect => 'Ver redirecciones',
            self::CreateRedirect => 'Crear redirecciones',
            self::UpdateRedirect => 'Editar redirecciones',
            self::DeleteRedirect => 'Eliminar redirecciones',
            self::ViewContactMessage => 'Ver mensajes de contacto',
            self::UpdateContactMessage => 'Cambiar estado de mensajes de contacto',
            self::DeleteContactMessage => 'Eliminar mensajes de contacto',
            self::ViewContactAssistantSettings => 'Ver Asistente de Contacto',
            self::UpdateContactAssistantSettings => 'Configurar Asistente de Contacto',
            self::TestMail => 'Enviar correo de prueba',
            self::ViewUsers => 'Ver usuarios',
            self::CreateUsers => 'Crear usuarios',
            self::UpdateUsers => 'Editar usuarios',
            self::DeleteUsers => 'Eliminar usuarios',
            self::ViewSettings => 'Ver configuración',
            self::UpdateSettings => 'Editar configuración',
            self::ViewMediaAsset => 'Ver biblioteca multimedia',
            self::CreateMediaAsset => 'Subir archivos multimedia',
            self::UpdateMediaAsset => 'Editar archivos multimedia',
            self::DeleteMediaAsset => 'Eliminar archivos multimedia',
            self::ViewPostCategory => 'Ver categorías',
            self::CreatePostCategory => 'Crear categorías',
            self::UpdatePostCategory => 'Editar categorías',
            self::DeletePostCategory => 'Eliminar categorías',
            self::ViewPost => 'Ver artículos',
            self::CreatePost => 'Crear artículos',
            self::UpdatePost => 'Editar artículos',
            self::DeletePost => 'Eliminar artículos',
            self::ViewService => 'Ver servicios',
            self::CreateService => 'Crear servicios',
            self::UpdateService => 'Editar servicios',
            self::DeleteService => 'Eliminar servicios',
            self::ViewTestimonial => 'Ver testimonios',
            self::CreateTestimonial => 'Crear testimonios',
            self::UpdateTestimonial => 'Editar testimonios',
            self::DeleteTestimonial => 'Eliminar testimonios',
            self::ViewFaq => 'Ver preguntas frecuentes',
            self::CreateFaq => 'Crear preguntas frecuentes',
            self::UpdateFaq => 'Editar preguntas frecuentes',
            self::DeleteFaq => 'Eliminar preguntas frecuentes',
            self::ViewLegalPage => 'Ver páginas legales',
            self::CreateLegalPage => 'Crear páginas legales',
            self::UpdateLegalPage => 'Editar páginas legales',
            self::DeleteLegalPage => 'Eliminar páginas legales',
            self::ViewProjectCategory => 'Ver categorías de proyectos',
            self::CreateProjectCategory => 'Crear categorías de proyectos',
            self::UpdateProjectCategory => 'Editar categorías de proyectos',
            self::DeleteProjectCategory => 'Eliminar categorías de proyectos',
            self::ViewProject => 'Ver proyectos',
            self::CreateProject => 'Crear proyectos',
            self::UpdateProject => 'Editar proyectos',
            self::DeleteProject => 'Eliminar proyectos',
        };
    }
}
