import React, { useState, useEffect, useRef } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { api } from '../../lib/api';
import { Menu, X, ChevronLeft, ChevronRight, Mail, MapPin, ArrowRight } from 'lucide-react';

export default function LandingPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [jobs, setJobs] = useState<any[]>([]);
  const [jobsLoading, setJobsLoading] = useState(true);
  const [currentSlide, setCurrentSlide] = useState(0);
  const [showTerms, setShowTerms] = useState(false);
  const [showPrivacy, setShowPrivacy] = useState(false);
  const touchStartX = useRef(0);

  useEffect(() => {
    api.getHomepageJobs()
      .then((data: any) => {
        if (data.success && data.jobs) setJobs(data.jobs);
      })
      .catch(() => {})
      .finally(() => setJobsLoading(false));
  }, []);

  const totalSlides = Math.ceil(jobs.length / 3);

  const handleTouchStart = (e: React.TouchEvent) => { touchStartX.current = e.changedTouches[0].screenX; };
  const handleTouchEnd = (e: React.TouchEvent) => {
    const diff = touchStartX.current - e.changedTouches[0].screenX;
    if (Math.abs(diff) > 50) {
      if (diff > 0 && currentSlide < totalSlides - 1) setCurrentSlide(p => p + 1);
      if (diff < 0 && currentSlide > 0) setCurrentSlide(p => p - 1);
    }
  };

  const scrollToJobs = () => {
    document.getElementById('browseJobs')?.scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <div className="bg-gray-50">
      {/* NAVBAR */}
      <nav className="bg-primary text-white px-4 md:px-6 py-4 sticky top-0 z-50 shadow-md">
        <div className="flex items-center justify-between max-w-7xl mx-auto">
          <div className="flex items-center gap-2 md:gap-3">
            <img src="https://static.readdy.ai/image/2d44f09b25f25697de5dc274e7f0a5a3/04242d6bffded145c33d09c9dcfae98c.png" alt="Norzagaray College Logo" className="w-10 h-10 md:w-12 md:h-12 object-contain" />
            <span className="text-xl md:text-2xl font-bold">NCHire</span>
          </div>
          <div className="hidden lg:flex items-center gap-8">
            <a href="#home" className="text-white hover:text-secondary transition-colors">Home</a>
            <a href="#mission" className="text-white hover:text-secondary transition-colors">Mission</a>
            <a href="#vision" className="text-white hover:text-secondary transition-colors">Vision</a>
            <a href="#about" className="text-white hover:text-secondary transition-colors">About NC</a>
          </div>
          <div className="hidden lg:flex items-center gap-3">
            {user ? (
              <button onClick={() => navigate(user.role ? '/app' : '/applicant/jobs')} className="px-4 py-2 bg-secondary text-primary hover:bg-yellow-300 transition-colors rounded-lg whitespace-nowrap font-medium">Dashboard</button>
            ) : (
              <>
                <Link to="/login" className="px-4 py-2 border border-white text-white hover:bg-white hover:text-primary transition-colors rounded-lg whitespace-nowrap">Sign In</Link>
                <Link to="/signup" className="px-4 py-2 bg-secondary text-primary hover:bg-yellow-300 transition-colors rounded-lg whitespace-nowrap font-medium">Sign Up</Link>
              </>
            )}
          </div>
          <button onClick={() => setMobileMenuOpen(true)} className="lg:hidden text-white focus:outline-none">
            <Menu className="w-6 h-6" />
          </button>
        </div>
      </nav>

      {/* Mobile Menu Overlay */}
      {mobileMenuOpen && <div className="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden" onClick={() => setMobileMenuOpen(false)} />}

      {/* Mobile Menu */}
      <div className={`fixed top-0 right-0 h-full w-64 bg-primary text-white z-50 shadow-2xl lg:hidden transform transition-transform duration-300 ${mobileMenuOpen ? 'translate-x-0' : 'translate-x-full'}`}>
        <div className="flex flex-col h-full">
          <div className="flex items-center justify-between p-4 border-b border-blue-700">
            <span className="text-xl font-bold">Menu</span>
            <button onClick={() => setMobileMenuOpen(false)} className="text-white"><X className="w-6 h-6" /></button>
          </div>
          <div className="flex flex-col gap-1 p-4">
            <a href="#home" onClick={() => setMobileMenuOpen(false)} className="px-4 py-3 text-white hover:bg-blue-700 rounded transition-colors">Home</a>
            <a href="#mission" onClick={() => setMobileMenuOpen(false)} className="px-4 py-3 text-white hover:bg-blue-700 rounded transition-colors">Mission</a>
            <a href="#vision" onClick={() => setMobileMenuOpen(false)} className="px-4 py-3 text-white hover:bg-blue-700 rounded transition-colors">Vision</a>
            <a href="#about" onClick={() => setMobileMenuOpen(false)} className="px-4 py-3 text-white hover:bg-blue-700 rounded transition-colors">About NC</a>
          </div>
          <div className="mt-auto p-4 border-t border-blue-700 space-y-3">
            <Link to="/login" onClick={() => setMobileMenuOpen(false)} className="block w-full px-4 py-3 border border-white text-white text-center hover:bg-white hover:text-primary transition-colors rounded-lg">Sign In</Link>
            <Link to="/signup" onClick={() => setMobileMenuOpen(false)} className="block w-full px-4 py-3 bg-secondary text-primary text-center hover:bg-yellow-300 transition-colors rounded-lg font-medium">Sign Up</Link>
          </div>
        </div>
      </div>

      {/* HERO */}
      <section id="home" className="relative bg-primary text-white min-h-[500px] md:min-h-[600px] flex items-center" style={{ backgroundImage: "url('assets/images/520382375_1065446909052636_3412465913398569974_n.jpg')", backgroundSize: 'cover', backgroundPosition: 'center' }}>
        <div className="absolute inset-0 bg-primary bg-opacity-80"></div>
        <div className="relative z-10 w-full px-4 md:px-6">
          <div className="max-w-7xl mx-auto">
            <div className="max-w-2xl">
              <h1 className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold mb-4 md:mb-6">
                YOUR JOB JOURNEY<br />
                <span className="text-secondary">BEGINS HERE</span>
              </h1>
              <p className="text-base md:text-lg lg:text-xl mb-6 md:mb-8 leading-relaxed">
                At NCHire, we connect talented individuals with top employers to help you take the next step in your career. Are you ready to start your journey?
              </p>
              <div className="flex flex-col sm:flex-row gap-3 md:gap-4">
                <Link to="/signup" className="px-6 md:px-8 py-3 md:py-4 border-2 border-white text-white hover:bg-white hover:text-primary transition-colors text-base md:text-lg font-semibold rounded-lg text-center">APPLY NOW</Link>
                <button onClick={scrollToJobs} className="px-6 md:px-8 py-3 md:py-4 bg-blue-600 text-white hover:bg-blue-700 transition-colors text-base md:text-lg font-semibold rounded-lg">LEARN MORE</button>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* BROWSE CAREER OPPORTUNITIES */}
      <section id="browseJobs" className="py-16 md:py-20 px-4 md:px-6">
        <div className="max-w-7xl mx-auto">
          <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold text-primary mb-8 md:mb-12">Browse Career<br />Opportunities</h2>

          {jobsLoading ? (
            <div className="text-center py-12">
              <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto"></div>
              <p className="text-gray-600 mt-4">Loading job opportunities...</p>
            </div>
          ) : jobs.length === 0 ? (
            <div className="text-center py-12 text-gray-500">
              <p>No job opportunities available at the moment.</p>
            </div>
          ) : (
            <div className="relative px-12 md:px-16 lg:px-20">
              <button onClick={() => setCurrentSlide(p => Math.max(0, p - 1))} disabled={currentSlide === 0} className="absolute left-0 md:left-2 top-1/2 -translate-y-1/2 z-20 bg-primary shadow-2xl rounded-full p-2 md:p-3 hover:bg-secondary hover:text-primary transition-all disabled:opacity-30 disabled:cursor-not-allowed text-white">
                <ChevronLeft className="w-5 h-5 md:w-6 md:h-6" />
              </button>

              <div className="overflow-hidden py-8" onTouchStart={handleTouchStart} onTouchEnd={handleTouchEnd}>
                <div className="flex transition-transform duration-500 ease-in-out gap-6" style={{ transform: `translateX(-${currentSlide * 100}%)` }}>
                  {jobs.map((job: any, i: number) => (
                    <div key={job.id} className="flex-shrink-0 w-full md:w-[calc((100%-1.5rem)/3)] bg-primary text-white p-6 md:p-8 rounded-2xl relative group hover:-translate-y-2 hover:shadow-2xl transition-all duration-300 overflow-hidden shadow-lg">
                      <div className="mb-4 md:mb-6">
                        <div className="w-full h-32 md:h-40 bg-blue-700 rounded mb-3 md:mb-4 flex items-center justify-center">
                          <span className="text-4xl md:text-6xl text-white opacity-50 font-bold">{(job.title || 'J')[0]}</span>
                        </div>
                      </div>
                      <div className="flex items-start justify-between gap-2 mb-2">
                        <h3 className="text-lg md:text-xl font-bold flex-1 line-clamp-2">{job.title}</h3>
                        <span className="flex-shrink-0 px-2 md:px-3 py-1 bg-secondary text-primary text-xs font-semibold rounded-full whitespace-nowrap">{job.type}</span>
                      </div>
                      <p className="text-secondary text-xs md:text-sm mb-2 font-semibold truncate">{job.department}</p>
                      <p className="text-gray-200 text-xs md:text-sm mb-3 md:mb-4 line-clamp-2">{job.description}</p>
                      <div className="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-3 text-xs md:text-sm text-gray-300 mb-12 md:mb-16">
                        <div className="flex items-center gap-1 min-w-0"><MapPin className="w-3 h-3 flex-shrink-0" /><span className="truncate">{job.location}</span></div>
                        <div className="flex items-center gap-1 min-w-0"><span className="truncate">{job.deadline}</span></div>
                      </div>
                      <div className="absolute bottom-4 md:bottom-6 right-4 md:right-6 w-10 h-10 md:w-12 md:h-12 flex items-center justify-center cursor-pointer text-white group-hover:translate-x-1 transition-transform" onClick={() => navigate('/signup')}>
                        <ArrowRight className="w-5 h-5 md:w-6 md:h-6" />
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <button onClick={() => setCurrentSlide(p => Math.min(totalSlides - 1, p + 1))} disabled={currentSlide >= totalSlides - 1} className="absolute right-0 md:right-2 top-1/2 -translate-y-1/2 z-20 bg-primary shadow-2xl rounded-full p-2 md:p-3 hover:bg-secondary hover:text-primary transition-all disabled:opacity-30 disabled:cursor-not-allowed text-white">
                <ChevronRight className="w-5 h-5 md:w-6 md:h-6" />
              </button>

              <div className="flex justify-center gap-2 mt-8">
                {Array.from({ length: totalSlides }).map((_, i) => (
                  <button key={i} onClick={() => setCurrentSlide(i)} className={`h-3 rounded-full transition-all duration-300 ${i === currentSlide ? 'bg-primary w-8' : 'bg-gray-300 w-3'}`} />
                ))}
              </div>
            </div>
          )}
        </div>
      </section>

      {/* MISSION */}
      <section id="mission" className="py-12 md:py-16 px-4 md:px-6 bg-white">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-8 md:mb-12">
            <h2 className="text-2xl md:text-3xl lg:text-4xl font-bold text-primary mb-3 md:mb-4">NORZAGARAY COLLEGE MISSION</h2>
            <p className="text-base md:text-lg lg:text-xl text-gray-600 max-w-3xl mx-auto">Norzagaray College envision itself to transform lives of individuals through life long learning and productivity.</p>
          </div>
        </div>
      </section>

      {/* VISION */}
      <section id="vision" className="py-12 md:py-16 px-4 md:px-6 bg-gray-100">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-8 md:mb-12">
            <h2 className="text-2xl md:text-3xl lg:text-4xl font-bold text-primary mb-3 md:mb-4">NORZAGARAY COLLEGE VISION</h2>
            <p className="text-base md:text-lg lg:text-xl text-gray-600">To be recognized nationally and internationally as a benchmark for excellence, innovation, integrity, and distinctiveness in bachelor's level education taught from global perspective.</p>
          </div>
        </div>
      </section>

      {/* ABOUT */}
      <section id="about" className="py-12 md:py-16 px-4 md:px-6 bg-white">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-8 md:mb-12">
            <h2 className="text-2xl md:text-3xl lg:text-4xl font-bold text-primary mb-3 md:mb-4">About Norzagaray College</h2>
            <p className="text-sm md:text-base lg:text-lg text-gray-700 max-w-4xl mx-auto leading-relaxed whitespace-pre-line">
              On December 21, 2004, Hon. Mayor Dr. Matilde A. Legaspi announced to the public that the Municipality of Norzagaray will soon establish a college of its own, a non sectarian institution dedicated to help the marginalized and underprivileged sector of the community by providing quality education at a minimum cost. While faced with the growing educational needs of the community, the founders, former Mayor Matilde A. Legaspi, M.D., and Ermelito V. dela Merced, M.D., started the consultations with different government agencies on how to put up an institution of higher learning. Soon, through SANGGUNIANG BAYAN ORDINANCE NO.2006-10, the Norzagaray College was established and the Norzagaray College Charter was promulgated. It was first a three storey building with eighteen rooms housing five (5) courses - Bachelor of Science in Computer Science, Bachelor of Science in Hotel and Restaurant Management, Bachelor of Science in Nursing, Bachelor of Science in Secondary Education and Elementary Education.

              {"\n\n"}On March 20, 2007, CHED Regional Office III issued a certificate recognizing Norzagaray College as one of the Local Community Colleges in Region III.

              {"\n\n"}In June 2007, Norzagaray College started providing quality education with its five programs. In 2010, the Commission on Higher Education (CHED) granted the necessary permits for B.S. Computer Science (No. GR-035 Series of 2010), B.S. Hotel and Restaurant Management (No. GR-031 Series of 2010), Bachelor of Elementary Education (No. GR-056 Series of 2010) and Bachelor of Secondary Education (No.GR-57 Series of 2010). While, In 2011, the CERTIFICATE OF PROGRAM COMPLIANCE was granted to Norzagaray College for the Bachelor of Science in Nursing Program. At present, the Norzagaray College is upgrading its standards to make its graduates globally competitive.
            </p>
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="py-12 md:py-16 px-4 md:px-6 bg-primary text-white">
        <div className="max-w-4xl mx-auto text-center">
          <h2 className="text-2xl md:text-3xl lg:text-4xl font-bold mb-4 md:mb-6">Are you ready to start your journey?</h2>
          <p className="text-base md:text-lg lg:text-xl mb-6 md:mb-8">We connect talented individuals with top employers to help you take the next step in your career.</p>
          <div className="flex flex-col sm:flex-row gap-3 md:gap-4 justify-center">
            <Link to="/signup" className="px-6 md:px-8 py-3 md:py-4 bg-secondary text-primary hover:bg-yellow-300 transition-colors text-base md:text-lg font-semibold rounded-lg text-center whitespace-nowrap">Start Your Application</Link>
            <button onClick={scrollToJobs} className="px-6 md:px-8 py-3 md:py-4 border-2 border-white text-white hover:bg-white hover:text-primary transition-colors text-base md:text-lg font-semibold rounded-lg whitespace-nowrap">Browse Opportunities</button>
          </div>
        </div>
      </section>

      {/* FOOTER */}
      <footer className="bg-gray-900 text-white py-8 md:py-12 px-4 md:px-6">
        <div className="max-w-7xl mx-auto">
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6 md:gap-8">
            <div>
              <div className="flex items-center gap-3 mb-4">
                <img src="https://static.readdy.ai/image/2d44f09b25f25697de5dc274e7f0a5a3/04242d6bffded145c33d09c9dcfae98c.png" alt="NCHire" className="w-10 h-10 object-contain" />
                <span className="text-xl font-bold">NCHire</span>
              </div>
              <p className="text-gray-400">Connecting passionate instructors with opportunities to inspire at Norzagaray College.</p>
            </div>
            <div>
              <h4 className="font-bold mb-4">Quick Links</h4>
              <ul className="space-y-2 text-gray-400">
                <li><a href="#home" className="hover:text-white transition-colors">Home</a></li>
                <li><a href="#mission" className="hover:text-white transition-colors">Mission</a></li>
                <li><a href="#vision" className="hover:text-white transition-colors">Vision</a></li>
                <li><a href="#about" className="hover:text-white transition-colors">About NC</a></li>
              </ul>
            </div>
            <div>
              <h4 className="font-bold mb-4">Legal</h4>
              <ul className="space-y-2 text-gray-400">
                <li><button onClick={() => setShowTerms(true)} className="hover:text-white transition-colors">Terms & Conditions</button></li>
                <li><button onClick={() => setShowPrivacy(true)} className="hover:text-white transition-colors">Privacy Policy</button></li>
              </ul>
            </div>
            <div>
              <h4 className="font-bold mb-4">Social Media</h4>
              <ul className="space-y-2 text-gray-400">
                <li><a href="https://www.facebook.com/norzagaraycollege2007" target="_blank" rel="noopener noreferrer" className="hover:text-white transition-colors">Facebook</a></li>
              </ul>
            </div>
            <div>
              <h4 className="font-bold mb-4">Contact</h4>
              <ul className="space-y-2 text-gray-400">
                <li className="flex items-center gap-2"><Mail className="w-4 h-4 flex-shrink-0" />norzagaraycollege.edu.ph</li>
                <li className="flex items-center gap-2"><MapPin className="w-4 h-4 flex-shrink-0" />Norzagaray, Bulacan</li>
              </ul>
            </div>
          </div>
          <div className="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
            <p>&copy; 2025 NCHire Research. All rights reserved.</p>
          </div>
        </div>
      </footer>

      {/* TERMS MODAL */}
      {showTerms && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" onClick={() => setShowTerms(false)}>
          <div className="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] overflow-hidden relative" onClick={e => e.stopPropagation()}>
            <button onClick={() => setShowTerms(false)} className="absolute top-3 right-3 md:top-4 md:right-4 text-gray-500 hover:text-gray-700 text-2xl z-10">&times;</button>
            <div className="overflow-y-auto max-h-[90vh] p-6 md:p-8">
              <h2 className="text-3xl md:text-4xl font-bold text-primary mb-4">Terms & Conditions</h2>
              <p className="text-gray-600 mb-6">Last Updated: January 2025</p>
              <div className="space-y-6 text-gray-700">
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">1. Acceptance of Terms</h3><p>By accessing and using the NCHire (Norzagaray College Hiring Portal), you accept and agree to be bound by the terms and provisions of this agreement.</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">2. Use of the Portal</h3><p>The NCHire portal is intended for job seekers interested in employment opportunities at Norzagaray College.</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">3. User Accounts</h3><p className="mb-2"><strong>Registration:</strong> Users must provide accurate, current, and complete information during registration.</p><p><strong>Account Security:</strong> You are responsible for all activities that occur under your account.</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">4. Application Submission</h3><p className="mb-2"><strong>Accuracy:</strong> All information submitted must be truthful, accurate, and complete.</p><p className="mb-2"><strong>Documents:</strong> Submit documents in PDF format, within 5MB size limits.</p><p><strong>Deadlines:</strong> Applications after the deadline will not be considered.</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">5. User Conduct</h3><p>Users agree not to:</p><ul className="list-disc ml-6 mt-2 space-y-1"><li>Submit false or fraudulent applications</li><li>Attempt unauthorized access to the system</li><li>Interfere with the portal's proper functioning</li></ul></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">6. Recruitment Process</h3><p className="mb-2"><strong>Review Timeline:</strong> Applications reviewed within 5-7 business days.</p><p className="mb-2"><strong>Selection:</strong> May include interviews, demonstration teaching, and examinations.</p><p><strong>Final Decision:</strong> All hiring decisions are at Norzagaray College's sole discretion.</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">7. Privacy</h3><p>Your use is governed by our <button onClick={() => { setShowTerms(false); setShowPrivacy(true); }} className="text-primary hover:underline">Privacy Policy</button>.</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">8. Limitation of Liability</h3><p>Norzagaray College shall not be liable for any damages arising from your use of the portal.</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">9. Governing Law</h3><p>These terms are governed by the laws of the Republic of the Philippines.</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">10. Contact</h3><div className="bg-gray-50 p-4 rounded-lg"><p><strong>Norzagaray College Human Resources</strong></p><p>Email: norzagaraycollege.edu.ph</p><p>Location: Norzagaray, Bulacan</p></div></section>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* PRIVACY MODAL */}
      {showPrivacy && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" onClick={() => setShowPrivacy(false)}>
          <div className="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] overflow-hidden relative" onClick={e => e.stopPropagation()}>
            <button onClick={() => setShowPrivacy(false)} className="absolute top-3 right-3 md:top-4 md:right-4 text-gray-500 hover:text-gray-700 text-2xl z-10">&times;</button>
            <div className="overflow-y-auto max-h-[90vh] p-6 md:p-8">
              <h2 className="text-3xl md:text-4xl font-bold text-primary mb-4">Privacy Policy</h2>
              <p className="text-gray-600 mb-6">Last Updated: January 2025</p>
              <div className="space-y-6 text-gray-700">
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">1. Introduction</h3><p>Norzagaray College is committed to protecting your privacy. This policy explains how we collect, use, and safeguard your information.</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">2. Information We Collect</h3><p className="mb-2"><strong>Personal Information:</strong> Name, email, phone, address, profile picture</p><p className="mb-2"><strong>Professional Information:</strong> Education, work experience, licenses, skills</p><p className="mb-2"><strong>Documents:</strong> Resume, transcripts, diplomas, certificates</p><p><strong>Technical:</strong> IP address, browser type, device information</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">3. How We Use Your Information</h3><ul className="list-disc ml-6 space-y-1"><li>Process and evaluate job applications</li><li>Send notifications about application status</li><li>Verify documents and information</li><li>Maintain your account</li><li>Ensure system security</li><li>Comply with legal obligations</li></ul></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">4. Information Sharing</h3><p className="mb-2"><strong>Internal:</strong> Shared with HR staff, department heads, and selection committees</p><p className="mb-2"><strong>External:</strong> Educational institutions and licensing boards for verification</p><p><strong>No Sale:</strong> We do not sell your personal information</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">5. Data Security</h3><ul className="list-disc ml-6 space-y-1"><li>SSL encryption</li><li>Password-protected accounts</li><li>Role-based access controls</li><li>Regular security audits</li><li>Secure file storage</li></ul></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">6. Data Retention</h3><p className="mb-1"><strong>Active Applications:</strong> Duration + 1 year</p><p className="mb-1"><strong>Hired Employees:</strong> Per employment records policy</p><p><strong>Unsuccessful:</strong> 1 year or upon request</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">7. Your Rights (Data Privacy Act 2012)</h3><ul className="list-disc ml-6 space-y-1"><li>Right to access your information</li><li>Right to correct inaccurate data</li><li>Right to request deletion</li><li>Right to object to processing</li><li>Right to data portability</li></ul></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">8. Cookies</h3><p>We use session cookies to maintain your login, remember preferences, and enhance user experience.</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">9. Email Communications</h3><p>We send essential emails about verification, application status, interviews, and hiring decisions.</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">10. Compliance</h3><p>We comply with the Data Privacy Act of 2012 (RA 10173) and its implementing rules.</p></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">11. Contact</h3><div className="bg-gray-50 p-4 rounded-lg"><p><strong>Data Protection Officer</strong></p><p>Norzagaray College</p><p>Email: norzagaraycollege.edu.ph</p><p>Location: Norzagaray, Bulacan</p></div></section>
                <section><h3 className="text-xl font-bold text-gray-900 mb-2">12. File a Complaint</h3><div className="bg-blue-50 border-l-4 border-primary p-4"><p><strong>National Privacy Commission (NPC)</strong></p><p className="text-sm">5th Floor, Philippines Postal Corporation Building</p><p className="text-sm">Liwasang Bonifacio, Manila 1000</p><p className="text-sm">Website: privacy.gov.ph</p></div></section>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
