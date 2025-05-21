import React from 'react';
import SectionWrapper from './SectionWrapper';
import { motion } from 'framer-motion';
import { Cpu, Database, Code, Smartphone, GitMerge, Layers, Server, Github } from 'lucide-react';
import { Button } from '@/components/ui/button';

const skillsData = [
  { name: 'PHP', icon: <Code size={32} />, level: 90 },
  { name: 'MySQL', icon: <Database size={32} />, level: 85 },
  { name: 'JavaScript', icon: <Code size={32} />, level: 80 },
  { name: 'React', icon: <Smartphone size={32} />, level: 75 },
  { name: 'React Native', icon: <Smartphone size={32} />, level: 70 },
  { name: 'Node.js', icon: <Server size={32} />, level: 65 },
  { name: 'Laravel', icon: <Layers size={32} />, level: 85 },
  { name: 'Django', icon: <Layers size={32} />, level: 60 },
  { name: 'Python', icon: <Code size={32} />, level: 70 },
  { name: 'Tailwind CSS', icon: <Code size={32} />, level: 90 },
  { name: 'Git', icon: <GitMerge size={32} />, level: 80 },
  { name: 'HTML', icon: <Code size={32} />, level: 95 },
  { name: 'Flask', icon: <Layers size={32} />, level: 50 },
];

const SkillCard = ({ name, icon, level, delay }) => {
  const cardVariants = {
    hidden: { opacity: 0, scale: 0.5, y: 50 },
    show: { 
      opacity: 1, 
      scale: 1, 
      y: 0,
      transition: { type: 'spring', stiffness: 100, delay } 
    },
  };

  return (
    <motion.div
      variants={cardVariants}
      className="flex flex-col items-center p-6 glassmorphism-card rounded-xl shadow-lg hover:shadow-primary/50 transition-shadow duration-300 transform hover:-translate-y-1"
      whileHover={{ scale: 1.05 }}
    >
      <div className="p-3 mb-3 rounded-full bg-primary/20 text-primary">
        {icon}
      </div>
      <h4 className="text-lg font-semibold mb-2 text-foreground">{name}</h4>
      <div className="w-full bg-muted rounded-full h-2.5">
        <motion.div
          className="bg-gradient-to-r from-primary to-accent h-2.5 rounded-full"
          initial={{ width: 0 }}
          whileInView={{ width: `${level}%` }}
          transition={{ duration: 1, delay: delay + 0.5, ease: "easeInOut" }}
          viewport={{ once: true }}
        />
      </div>
    </motion.div>
  );
};

const GitHubInsights = () => {
  const insightVariants = {
    hidden: { opacity: 0, y: 20 },
    show: { opacity: 1, y: 0, transition: { duration: 0.5, delay: 0.2 } }
  };

  return (
    <motion.div 
      className="mt-16 text-center"
      variants={insightVariants}
      initial="hidden"
      whileInView="show"
      viewport={{ once: true }}
    >
      <h3 className="text-3xl font-semibold mb-8 text-gradient animate-text-gradient">GitHub Insights</h3>
      <p className="text-muted-foreground max-w-2xl mx-auto mb-8">
        Acompanhe minhas atividades e contribuições diretamente no GitHub.
      </p>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <motion.div 
          className="glassmorphism-card p-6 rounded-xl flex flex-col items-center justify-center"
          whileHover={{ y: -5, boxShadow: "0px 10px 20px -5px hsl(var(--primary)/0.2)" }}
        >
          <h4 className="text-xl font-semibold text-primary mb-3">Visão Geral</h4>
          <img 
            src="https://github-readme-stats.vercel.app/api?username=kellyson71&show_icons=true&theme=transparent&icon_color=8A2BE2&text_color=CFD8DC&title_color=4A00E0&hide_border=true&bg_color=00000000"
            alt="Estatísticas do GitHub de Kellyson71"
            class="rounded-md w-full max-w-md"
           src="https://images.unsplash.com/photo-1681511346076-da60ca823409" />
        </motion.div>
        <motion.div 
          className="glassmorphism-card p-6 rounded-xl flex flex-col items-center justify-center"
          whileHover={{ y: -5, boxShadow: "0px 10px 20px -5px hsl(var(--accent)/0.2)" }}
        >
          <h4 className="text-xl font-semibold text-accent mb-3">Linguagens Mais Usadas</h4>
          <img 
            src="https://github-readme-stats.vercel.app/api/top-langs/?username=kellyson71&layout=compact&theme=transparent&icon_color=8A2BE2&text_color=CFD8DC&title_color=4A00E0&hide_border=true&bg_color=00000000&langs_count=6"
            alt="Linguagens mais usadas por Kellyson71 no GitHub"
            class="rounded-md w-full max-w-md"
           src="https://images.unsplash.com/photo-1516259762381-22954d7d3ad2" />
        </motion.div>
      </div>
       <motion.div 
          className="glassmorphism-card p-6 rounded-xl flex flex-col items-center justify-center"
          whileHover={{ y: -5, boxShadow: "0px 10px 20px -5px hsl(var(--secondary)/0.2)" }}
        >
          <h4 className="text-xl font-semibold text-secondary mb-3">GitHub Streak</h4>
           <img 
            src="https://github-readme-streak-stats.herokuapp.com/?user=kellyson71&theme=transparent&hide_border=true&background=00000000&stroke=8A2BE2&ring=4A00E0&fire=FF7F7F&currStreakNum=CFD8DC&sideNums=CFD8DC&currStreakLabel=CFD8DC&sideLabels=CFD8DC&dates=CFD8DC"
            alt="GitHub Streak de Kellyson71"
            class="rounded-md w-full max-w-md"
           src="https://images.unsplash.com/photo-1654277041218-84424c78f0ae" />
        </motion.div>
      <Button asChild size="lg" className="mt-8 bg-gradient-to-r from-primary to-secondary hover:opacity-90 transition-opacity duration-300 shadow-lg transform hover:scale-105">
        <a href="https://github.com/kellyson71" target="_blank" rel="noopener noreferrer">
          <Github size={20} className="mr-2" /> Ver Perfil no GitHub
        </a>
      </Button>
    </motion.div>
  );
};


const SkillsContent = () => {
  return (
    <>
      <motion.h2 
        initial={{ opacity: 0, y: -20 }}
        whileInView={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        viewport={{ once: true }}
        className="section-title"
      >
        Habilidades Técnicas
      </motion.h2>
      <motion.p 
        initial={{ opacity: 0, y:20 }}
        whileInView={{ opacity: 1, y:0 }}
        transition={{ duration: 0.5, delay: 0.2 }}
        viewport={{ once: true }}
        className="text-center text-lg text-muted-foreground mb-12 max-w-2xl mx-auto"
      >
        Com paixão por desenvolvimento e aprendizado contínuo, possuo proficiência em diversas tecnologias modernas.
      </motion.p>
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
        {skillsData.map((skill, index) => (
          <SkillCard key={skill.name} {...skill} delay={index * 0.05} />
        ))}
      </div>
      <GitHubInsights />
    </>
  );
};

const Skills = SectionWrapper(SkillsContent, 'skills');
export default Skills;