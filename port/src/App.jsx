
import React, { Suspense, lazy } from 'react';
import { Toaster } from '@/components/ui/toaster';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import { motion } from 'framer-motion';

const Hero = lazy(() => import('@/components/Hero'));
const About = lazy(() => import('@/components/About'));
const Skills = lazy(() => import('@/components/Skills'));
const Projects = lazy(() => import('@/components/Projects'));
const Education = lazy(() => import('@/components/Education'));
const Contact = lazy(() => import('@/components/Contact'));

const LoadingFallback = () => (
  <div className="flex justify-center items-center h-screen">
    <motion.div
      animate={{ rotate: 360 }}
      transition={{ duration: 1, repeat: Infinity, ease: "linear" }}
      className="w-16 h-16 border-4 border-t-primary border-transparent rounded-full"
    />
  </div>
);

function App() {
  return (
    <div className="flex flex-col min-h-screen bg-background font-sans">
      <Header />
      <main className="flex-grow">
        <Suspense fallback={<LoadingFallback />}>
          <Hero />
          <About />
          <Skills />
          <Projects />
          <Education />
          <Contact />
        </Suspense>
      </main>
      <Footer />
      <Toaster />
    </div>
  );
}

export default App;
  