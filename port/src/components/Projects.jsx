import React from 'react';
import SectionWrapper from './SectionWrapper';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Github, ExternalLink, Zap } from 'lucide-react';
import { motion } from 'framer-motion';

const projectsData = [
  {
    title: 'Estágio Pau dos Ferros',
    description: 'Uma plataforma completa para gerenciar estágios na Prefeitura, facilitando o fluxo entre estudantes, instituições e a administração.',
    imageKey: 'estagio-pdf',
    imageDesc: 'Interface da plataforma Estágio Pau dos Ferros',
    repoUrl: 'https://github.com/Kellyson/estagio',
    liveUrl: 'https://estagiopaudosferros.com/',
    tags: ['PHP', 'Laravel', 'MySQL', 'JavaScript']
  },
  {
    title: 'Protocolo SEAD',
    description: 'Sistema de protocolo digital para organizar e rastrear processos administrativos na Secretaria de Administração.',
    imageKey: 'protocolo-sead',
    imageDesc: 'Dashboard do Sistema de Protocolo SEAD',
    repoUrl: 'https://github.com/Kellyson/protocolo-sead',
    liveUrl: 'https://protocolosead.com/protocolo',
    tags: ['PHP', 'Laravel', 'React', 'MySQL']
  },
  {
    title: 'Pau dos Ferros 360 Graus',
    description: 'Projeto interativo que oferece uma visualização 360° da cidade, promovendo o turismo e a exploração digital.',
    imageKey: 'pdf-360',
    imageDesc: 'Visualização 360 graus de um ponto turístico de Pau dos Ferros',
    repoUrl: 'https://github.com/Kellyson/paudosferros360',
    liveUrl: 'https://paudosferros360graus.com.br',
    tags: ['JavaScript', 'HTML', 'CSS', 'A-Frame']
  },
  {
    title: 'Supaco',
    description: 'Sistema de suporte acadêmico desenvolvido para auxiliar estudantes com materiais e acompanhamento.',
    imageKey: 'supaco',
    imageDesc: 'Tela inicial do sistema Supaco',
    repoUrl: 'https://github.com/Kellyson/supaco',
    liveUrl: 'https://suap2.estagiopaudosferros.com',
    tags: ['Python', 'Django', 'HTML', 'CSS']
  },
  {
    title: 'Demutran',
    description: 'Sistema para gestão de trânsito municipal, incluindo ocorrências e fiscalização.',
    imageKey: 'demutran',
    imageDesc: 'Interface do sistema Demutran',
    repoUrl: 'https://github.com/Kellyson/demutran',
    liveUrl: 'https://demutranpaudosferros.com.br',
    tags: ['PHP', 'JavaScript', 'MySQL']
  }
];

const ProjectCard = ({ project, index }) => {
  const cardVariants = {
    hidden: { opacity: 0, y: 50, scale: 0.9 },
    show: { 
      opacity: 1, 
      y: 0, 
      scale: 1,
      transition: { type: 'spring', stiffness: 50, delay: index * 0.1 }
    }
  };

  return (
    <motion.div variants={cardVariants} whileHover={{ y: -10, boxShadow: "0px 20px 30px -10px hsl(var(--primary) / 0.3)"}}>
      <Card className="h-full flex flex-col overflow-hidden glassmorphism-card border-primary/30 hover:border-accent transition-all duration-300 group">
        <div className="aspect-video overflow-hidden">
          <img  
            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" 
            alt={project.imageDesc} src="https://images.unsplash.com/photo-1697256200022-f61abccad430" />
        </div>
        <CardHeader>
          <CardTitle className="text-gradient text-2xl group-hover:text-accent transition-colors">{project.title}</CardTitle>
          <div className="flex flex-wrap gap-2 mt-2">
            {project.tags.map(tag => (
              <span key={tag} className="px-3 py-1 text-xs bg-primary/20 text-primary rounded-full group-hover:bg-accent/20 group-hover:text-accent transition-colors">{tag}</span>
            ))}
          </div>
        </CardHeader>
        <CardContent className="flex-grow">
          <CardDescription className="text-foreground/80">{project.description}</CardDescription>
        </CardContent>
        <CardFooter className="gap-3 p-4">
          {project.repoUrl && (
            <Button variant="outline" asChild className="border-primary text-primary hover:bg-primary/10 hover:text-primary flex-1 group-hover:border-accent group-hover:text-accent group-hover:bg-accent/10 transition-all duration-300 transform hover:scale-105">
              <a href={project.repoUrl} target="_blank" rel="noopener noreferrer">
                <Github size={18} className="mr-2" /> Repositório
              </a>
            </Button>
          )}
          {project.liveUrl && (
            <Button variant="default" asChild className="bg-gradient-to-r from-primary to-secondary hover:from-secondary hover:to-accent text-primary-foreground hover:shadow-lg hover:shadow-accent/50 flex-1 transition-all duration-300 transform hover:scale-105">
              <a href={project.liveUrl} target="_blank" rel="noopener noreferrer">
                <ExternalLink size={18} className="mr-2" /> Ver Online
              </a>
            </Button>
          )}
        </CardFooter>
      </Card>
    </motion.div>
  );
};

const ProjectsContent = () => {
  return (
    <>
      <motion.h2 
        initial={{ opacity: 0, y: -20 }}
        whileInView={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        viewport={{ once: true }}
        className="section-title"
      >
        Projetos em Destaque
      </motion.h2>
      <motion.p 
        initial={{ opacity: 0, y:20 }}
        whileInView={{ opacity: 1, y:0 }}
        transition={{ duration: 0.5, delay: 0.2 }}
        viewport={{ once: true }}
        className="text-center text-lg text-muted-foreground mb-12 max-w-2xl mx-auto"
      >
        Aqui estão alguns dos projetos que desenvolvi, demonstrando minhas habilidades em diferentes tecnologias e soluções.
      </motion.p>
      <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
        {projectsData.map((project, index) => (
          <ProjectCard key={project.title} project={project} index={index} />
        ))}
      </div>
    </>
  );
};

const Projects = SectionWrapper(ProjectsContent, 'projects');
export default Projects;